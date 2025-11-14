<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Deliverable;
use App\Models\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PgApiResponseHelpers;
use Tests\TestCase;

class FileEndpointsTest extends TestCase
{
    use PgApiResponseHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2025-03-01 12:00:00');
        Storage::fake('public');
        config(['filesystems.default' => 'public']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_index_returns_expected_payload(): void
    {
        $file = $this->createFileWithDeliverable('propuesta.pdf');

        $response = $this->getJson('/api/pg/files');

        $files = File::orderByDesc('updated_at')->get();

        $response->assertOk()->assertExactJson($files->map(fn (File $item) => [
            'id' => $item->id,
            'name' => $item->name,
            'extension' => $item->extension,
            'url' => $item->url,
        ])->values()->all());

        $this->assertSame('propuesta.pdf', $file->fresh()->name);
    }

    public function test_show_returns_expected_resource(): void
    {
        $file = $this->createFileWithDeliverable('propuesta.pdf');

        $response = $this->getJson("/api/pg/files/{$file->id}");

        $file->load([
            'deliverables.phase.period',
            'submissions',
            'repositoryProjects',
            'proposals',
        ]);

        $response->assertOk()->assertExactJson($this->fileResource($file));
    }

    public function test_update_allows_name_and_file_changes_only(): void
    {
        $file = $this->createFileWithDeliverable('propuesta.pdf');

        $payload = [
            'name' => 'planilla.xlsx',
            'extension' => 'xlsx',
        ];

        $response = $this->putJson("/api/pg/files/{$file->id}", $payload);

        $file->refresh();
        $file->load([
            'deliverables.phase.period',
            'submissions',
            'repositoryProjects',
            'proposals',
        ]);

        $response->assertOk()->assertExactJson($this->fileResource($file));

        $this->assertSame('planilla.xlsx', $file->name);
        $this->assertSame('xlsx', $file->extension);
    }

    public function test_destroy_deletes_file_and_storage_object(): void
    {
        $file = $this->createFileWithDeliverable('propuesta.pdf');

        Storage::disk('public')->put($file->path, 'content');
        $this->assertTrue(Storage::disk('public')->exists($file->path));

        $this->deleteJson("/api/pg/files/{$file->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('files', ['id' => $file->id]);
        $this->assertFalse(Storage::disk('public')->exists($file->path));
    }

    public function test_dropdown_returns_updated_at_desc_order(): void
    {
        $first = File::create([
            'name' => 'older.pdf',
            'extension' => 'pdf',
            'url' => 'https://files.test/older.pdf',
            'disk' => 'public',
            'path' => 'pg/uploads/older.pdf',
        ]);
        Carbon::setTestNow('2025-03-02 12:00:00');
        $second = File::create([
            'name' => 'newer.pdf',
            'extension' => 'pdf',
            'url' => 'https://files.test/newer.pdf',
            'disk' => 'public',
            'path' => 'pg/uploads/newer.pdf',
        ]);

        $this->getJson('/api/pg/files/dropdown')
            ->assertOk()
            ->assertExactJson([
                ['value' => $second->id, 'label' => 'newer.pdf'],
                ['value' => $first->id, 'label' => 'older.pdf'],
            ]);
    }

    private function createDeliverable(string $name): Deliverable
    {
        $period = AcademicPeriod::create([
            'name' => '2025-1',
            'start_date' => '2025-01-01',
            'end_date' => '2025-06-30',
        ]);

        $phase = $period->phases()->create([
            'name' => 'PG I',
            'start_date' => '2025-01-01',
            'end_date' => '2025-06-30',
        ]);

        return $phase->deliverables()->create([
            'name' => $name,
            'description' => 'Descripcion',
            'due_date' => '2025-04-15 18:00:00',
        ]);
    }

    private function createFileWithDeliverable(string $fileName): File
    {
        $deliverable = $this->createDeliverable('Entrega 1');

        $file = File::create([
            'name' => $fileName,
            'extension' => pathinfo($fileName, PATHINFO_EXTENSION),
            'url' => 'https://files.test/'.$fileName,
            'disk' => 'public',
            'path' => 'pg/uploads/'.$fileName,
        ]);

        $file->deliverables()->sync([$deliverable->id]);

        return $file->fresh();
    }
}
