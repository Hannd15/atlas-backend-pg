<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicPeriodState;
use App\Models\Deliverable;
use App\Models\File;
use App\Models\Phase;
use App\Services\FileStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\PgApiResponseHelpers;
use Tests\TestCase;

class DeliverableEndpointsTest extends TestCase
{
    use PgApiResponseHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2025-03-01 10:00:00');

        $this->partialMock(FileStorageService::class, function ($mock): void {
            $mock->shouldReceive('storeUploadedFiles')->andReturn(collect());
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_index_returns_expected_payload(): void
    {
        [$phase] = $this->createPhaseWithPeriod();

        $deliverable = $phase->deliverables()->create([
            'name' => 'Entrega 1',
            'description' => 'Documento PDF',
            'due_date' => '2025-04-15 18:00:00',
        ]);

        $file = File::create([
            'name' => 'propuesta.pdf',
            'extension' => 'pdf',
            'url' => 'https://files.test/propuesta.pdf',
            'disk' => 'public',
            'path' => 'pg/uploads/propuesta.pdf',
        ]);
        $deliverable->files()->attach($file);

        $deliverable->refresh();

        $response = $this->getJson('/api/pg/deliverables');

        $expected = Deliverable::with('phase.period', 'files')->orderByDesc('updated_at')->get()
            ->map(fn (Deliverable $item) => $this->deliverableResource($item))
            ->values()
            ->all();

        $response->assertOk()->assertExactJson($expected);
    }

    public function test_store_creates_single_deliverable(): void
    {
        [$phase] = $this->createPhaseWithPeriod();

        $existingFile = File::create([
            'name' => 'diagrama.pdf',
            'extension' => 'pdf',
            'url' => 'https://files.test/diagrama.pdf',
            'disk' => 'public',
            'path' => 'pg/uploads/diagrama.pdf',
        ]);

        $payload = [
            'phase_id' => $phase->id,
            'name' => 'Entrega 1',
            'description' => 'Documento PDF',
            'due_date' => '2025-04-20 17:00:00',
            'file_ids' => [$existingFile->id],
        ];

        $response = $this->postJson('/api/pg/deliverables', $payload);

        $deliverable = Deliverable::firstOrFail()->load('phase.period', 'files');

        $response->assertCreated()->assertExactJson($this->deliverableResource($deliverable));

        $this->assertDatabaseHas('deliverables', [
            'phase_id' => $phase->id,
            'name' => 'Entrega 1',
        ]);
        $this->assertDatabaseHas('deliverable_files', [
            'deliverable_id' => $deliverable->id,
            'file_id' => $existingFile->id,
        ]);
    }

    public function test_store_accepts_batch_payload(): void
    {
        [$phase] = $this->createPhaseWithPeriod();

        $payload = [
            'deliverables' => [
                [
                    'phase_id' => $phase->id,
                    'name' => 'Entrega 1',
                    'description' => 'Doc 1',
                    'due_date' => '2025-04-20 17:00:00',
                ],
                [
                    'phase_id' => $phase->id,
                    'name' => 'Entrega 2',
                    'description' => 'Doc 2',
                    'due_date' => '2025-04-25 17:00:00',
                ],
            ],
        ];

        $response = $this->postJson('/api/pg/deliverables', $payload);

        $deliverables = Deliverable::with('phase.period', 'files')->orderBy('id')->get();

        $response->assertCreated()->assertExactJson($deliverables->map(fn (Deliverable $item) => $this->deliverableResource($item))->values()->all());

        $this->assertCount(2, $deliverables);
    }

    public function test_show_returns_expected_resource(): void
    {
        [$phase] = $this->createPhaseWithPeriod();

        $deliverable = $phase->deliverables()->create([
            'name' => 'Entrega 1',
            'description' => 'Documento PDF',
            'due_date' => '2025-04-15 18:00:00',
        ]);

        $response = $this->getJson("/api/pg/deliverables/{$deliverable->id}");

        $deliverable->load('phase.period', 'files');

        $response->assertOk()->assertExactJson($this->deliverableResource($deliverable));
    }

    public function test_update_syncs_files_and_basic_fields(): void
    {
        [$phase] = $this->createPhaseWithPeriod();
        $otherPhase = $this->createPhaseWithPeriod(name: 'PG II')[0];

        $deliverable = $phase->deliverables()->create([
            'name' => 'Entrega 1',
            'description' => 'Documento PDF',
            'due_date' => '2025-04-15 18:00:00',
        ]);

        $fileA = File::create([
            'name' => 'propuesta.pdf',
            'extension' => 'pdf',
            'url' => 'https://files.test/propuesta.pdf',
            'disk' => 'public',
            'path' => 'pg/uploads/propuesta.pdf',
        ]);
        $fileB = File::create([
            'name' => 'anexos.zip',
            'extension' => 'zip',
            'url' => 'https://files.test/anexos.zip',
            'disk' => 'public',
            'path' => 'pg/uploads/anexos.zip',
        ]);

        $payload = [
            'name' => 'Entrega Final',
            'phase_id' => $otherPhase->id,
            'description' => 'Documento final',
            'due_date' => '2025-05-01 12:00:00',
            'file_ids' => [$fileA->id, $fileB->id],
        ];

        $response = $this->putJson("/api/pg/deliverables/{$deliverable->id}", $payload);

        $deliverable->refresh()->load('phase.period', 'files');

        $response->assertOk()->assertExactJson($this->deliverableResource($deliverable));

        $this->assertSame('Entrega Final', $deliverable->name);
        $this->assertSame('Documento final', $deliverable->description);
        $this->assertSame('2025-05-01 12:00:00', $deliverable->due_date?->toDateTimeString());
        $this->assertEqualsCanonicalizing([$fileA->id, $fileB->id], $deliverable->files->pluck('id')->all());
    }

    public function test_destroy_removes_deliverable(): void
    {
        [$phase] = $this->createPhaseWithPeriod();

        $deliverable = $phase->deliverables()->create([
            'name' => 'Entrega 1',
            'due_date' => '2025-04-15 18:00:00',
        ]);

        $this->deleteJson("/api/pg/deliverables/{$deliverable->id}")
            ->assertOk()
            ->assertExactJson(['message' => 'Deliverable deleted successfully']);

        $this->assertDatabaseMissing('deliverables', ['id' => $deliverable->id]);
    }

    public function test_dropdown_returns_updated_at_desc_order(): void
    {
        [$phase] = $this->createPhaseWithPeriod();

        $older = $phase->deliverables()->create([
            'name' => 'Entrega 1',
            'description' => 'Doc',
            'due_date' => '2025-04-10 12:00:00',
        ]);
        Carbon::setTestNow('2025-03-02 10:00:00');

        $newer = $phase->deliverables()->create([
            'name' => 'Entrega 2',
            'description' => 'Doc 2',
            'due_date' => '2025-04-20 12:00:00',
        ]);

        $this->getJson('/api/pg/deliverables/dropdown')
            ->assertOk()
            ->assertExactJson([
                ['value' => $newer->id, 'label' => 'Entrega 2'],
                ['value' => $older->id, 'label' => 'Entrega 1'],
            ]);
    }

    /**
     * @return array{0: Phase}
     */
    private function createPhaseWithPeriod(string $name = 'PG I'): array
    {
        $state = AcademicPeriodState::first() ?? AcademicPeriodState::create([
            'name' => 'Active',
            'description' => 'State',
        ]);

        $period = AcademicPeriod::create([
            'name' => '2025-1',
            'start_date' => '2025-01-01',
            'end_date' => '2025-06-30',
            'state_id' => $state->id,
        ]);

        $phase = $period->phases()->create([
            'name' => $name,
            'start_date' => '2025-01-01',
            'end_date' => '2025-06-30',
        ]);

        return [$phase];
    }
}
