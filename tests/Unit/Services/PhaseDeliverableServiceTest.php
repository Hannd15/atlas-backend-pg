<?php

namespace Tests\Unit\Services;

use App\Models\Deliverable;
use App\Models\File;
use App\Models\Phase;
use App\Services\FileStorageService;
use App\Services\PhaseDeliverableService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhaseDeliverableServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Carbon::setTestNow('2025-01-10 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_replace_deliverables_rebuilds_phase_and_links_files(): void
    {
        $phase = Phase::factory()->create();
        $oldDeliverable = Deliverable::factory()->create(['phase_id' => $phase->id]);
        $oldFile = File::create([
            'name' => 'old.pdf',
            'extension' => 'pdf',
            'url' => 'https://files.test/old.pdf',
            'disk' => 'public',
            'path' => 'pg/uploads/old.pdf',
        ]);
        $oldDeliverable->files()->sync([$oldFile->id]);

        $existingFile = File::create([
            'name' => 'guidelines.pdf',
            'extension' => 'pdf',
            'url' => 'https://files.test/guidelines.pdf',
            'disk' => 'public',
            'path' => 'pg/uploads/guidelines.pdf',
        ]);

        $newUploadModel = File::create([
            'name' => 'template.docx',
            'extension' => 'docx',
            'url' => 'https://files.test/template.docx',
            'disk' => 'public',
            'path' => 'pg/uploads/template.docx',
        ]);

        $upload = UploadedFile::fake()->create('template.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $fakeStorage = new class([$newUploadModel]) extends FileStorageService
        {
            /** @var array<int, \Illuminate\Support\Collection<int, File>> */
            public array $responses;

            /** @var array<int, array<int, mixed>> */
            public array $calls = [];

            public function __construct(array $responses)
            {
                $this->responses = array_map(fn ($response) => $response instanceof Collection ? $response : collect([$response]), $responses);
            }

            public function storeUploadedFiles(array $files): Collection
            {
                $this->calls[] = $files;

                if (empty($files)) {
                    return collect();
                }

                return array_shift($this->responses) ?? collect();
            }
        };

        $service = new PhaseDeliverableService($fakeStorage);

        $payload = [
            [
                'name' => 'First Deliverable',
                'description' => 'Primary description',
                'due_date' => '2025-02-01 18:00:00',
                'file_ids' => [$existingFile->id],
            ],
            [
                'description' => null,
                'due_date' => '2025-02-15 12:00:00',
            ],
        ];

        $uploads = [
            ['files' => [$upload]],
            [],
        ];

        $result = $service->replaceDeliverables($phase, $payload, $uploads);

        $this->assertCount(2, $result);

        $this->assertDatabaseMissing('deliverables', ['id' => $oldDeliverable->id]);

        $first = $result->first();
        $this->assertSame('First Deliverable', $first->name);
        $this->assertSame('Primary description', $first->description);
        $this->assertSame('2025-02-01 18:00:00', optional($first->due_date)->format('Y-m-d H:i:s'));
        $this->assertEqualsCanonicalizing([
            $existingFile->id,
            $newUploadModel->id,
        ], $first->files->pluck('id')->all());

        $second = $result->get(1);
        $this->assertSame('Entrega 2', $second->name);
        $this->assertNull($second->description);
        $this->assertTrue($second->files->isEmpty());
    }

    public function test_clone_from_phase_duplicates_deliverables_and_relations(): void
    {
        $sourcePhase = Phase::factory()->create();
        $targetPhase = Phase::factory()->create();

        $file = File::create([
            'name' => 'rubric.pdf',
            'extension' => 'pdf',
            'url' => 'https://files.test/rubric.pdf',
            'disk' => 'public',
            'path' => 'pg/uploads/rubric.pdf',
        ]);

        $sourceDeliverable = Deliverable::factory()->create([
            'phase_id' => $sourcePhase->id,
            'name' => 'Existing Deliverable',
            'description' => 'Details',
            'due_date' => '2025-03-10 10:00:00',
        ]);
        $sourceDeliverable->files()->sync([$file->id]);

        $service = new PhaseDeliverableService(new class([]) extends FileStorageService {});

        $cloned = $service->cloneFromPhase($targetPhase, $sourcePhase);

        $this->assertCount(1, $cloned);

        $newDeliverable = $cloned->first();
        $this->assertSame('Existing Deliverable', $newDeliverable->name);
        $this->assertSame('Details', $newDeliverable->description);
        $this->assertSame('2025-03-10 10:00:00', optional($newDeliverable->due_date)->format('Y-m-d H:i:s'));
        $this->assertEquals([$file->id], $newDeliverable->files->pluck('id')->all());

        $this->assertEquals(1, $targetPhase->deliverables()->count());
        $this->assertEquals(1, $sourcePhase->deliverables()->count());
    }
}
