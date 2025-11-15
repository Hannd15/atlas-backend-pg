<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Deliverable;
use App\Models\File;
use App\Models\Phase;
use App\Models\Rubric;
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

        $rubric = Rubric::create([
            'name' => 'Calidad del documento',
            'description' => 'Evalúa claridad y estructura',
            'min_value' => 0,
            'max_value' => 5,
        ]);
        $deliverable->rubrics()->attach($rubric);

        $deliverable->refresh();

        $response = $this->getJson($this->deliverablesRoute($phase));

        $expected = $phase->deliverables()->with('phase.period', 'files', 'rubrics')->orderByDesc('updated_at')->get()
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

        $rubric = Rubric::create([
            'name' => 'Impacto',
            'description' => 'Evalúa impacto del proyecto',
            'min_value' => 0,
            'max_value' => 10,
        ]);

        $payload = [
            'name' => 'Entrega 1',
            'description' => 'Documento PDF',
            'due_date' => '2025-04-20 17:00:00',
            'file_ids' => [$existingFile->id],
            'rubric_ids' => [$rubric->id],
        ];

        $response = $this->postJson($this->deliverablesRoute($phase), $payload);

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
        $this->assertDatabaseHas('rubric_deliverables', [
            'deliverable_id' => $deliverable->id,
            'rubric_id' => $rubric->id,
        ]);
    }

    public function test_store_rejects_batch_payload(): void
    {
        [$phase] = $this->createPhaseWithPeriod();

        $rubricOne = Rubric::create([
            'name' => 'Claridad',
            'description' => 'Evalúa claridad',
            'min_value' => 0,
            'max_value' => 5,
        ]);

        $rubricTwo = Rubric::create([
            'name' => 'Investigación',
            'description' => 'Evalúa investigación',
            'min_value' => 0,
            'max_value' => 5,
        ]);

        $payload = [
            'deliverables' => [
                [
                    'name' => 'Entrega 1',
                    'description' => 'Doc 1',
                    'due_date' => '2025-04-20 17:00:00',
                    'rubric_ids' => [$rubricOne->id],
                ],
                [
                    'name' => 'Entrega 2',
                    'description' => 'Doc 2',
                    'due_date' => '2025-04-25 17:00:00',
                    'rubric_ids' => [$rubricTwo->id],
                ],
            ],
        ];

        $response = $this->postJson($this->deliverablesRoute($phase), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_show_returns_expected_resource(): void
    {
        [$phase] = $this->createPhaseWithPeriod();

        $deliverable = $phase->deliverables()->create([
            'name' => 'Entrega 1',
            'description' => 'Documento PDF',
            'due_date' => '2025-04-15 18:00:00',
        ]);

        $rubric = Rubric::create([
            'name' => 'Calidad',
            'description' => 'Evalúa calidad',
            'min_value' => 0,
            'max_value' => 5,
        ]);
        $deliverable->rubrics()->attach($rubric);

        $response = $this->getJson($this->deliverableRoute($deliverable));

        $deliverable->load('phase.period', 'files', 'rubrics');

        $response->assertOk()->assertExactJson($this->deliverableResource($deliverable));
    }

    public function test_update_syncs_files_and_basic_fields(): void
    {
        [$phase] = $this->createPhaseWithPeriod();

        $deliverable = $phase->deliverables()->create([
            'name' => 'Entrega 1',
            'description' => 'Documento PDF',
            'due_date' => '2025-04-15 18:00:00',
        ]);

        $initialRubric = Rubric::create([
            'name' => 'Rúbrica inicial',
            'description' => 'Config inicial',
            'min_value' => 0,
            'max_value' => 5,
        ]);
        $deliverable->rubrics()->attach($initialRubric);

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

        $rubricA = Rubric::create([
            'name' => 'Contenido',
            'description' => 'Evalúa contenido',
            'min_value' => 0,
            'max_value' => 10,
        ]);
        $rubricB = Rubric::create([
            'name' => 'Presentación',
            'description' => 'Evalúa presentación',
            'min_value' => 0,
            'max_value' => 10,
        ]);

        $payload = [
            'name' => 'Entrega Final',
            'description' => 'Documento final',
            'due_date' => '2025-05-01 12:00:00',
            'file_ids' => [$fileA->id, $fileB->id],
            'rubric_ids' => [$rubricA->id, $rubricB->id],
        ];

        $response = $this->putJson($this->deliverableRoute($deliverable), $payload);

        $deliverable->refresh()->load('phase.period', 'files', 'rubrics');

        $response->assertOk()->assertExactJson($this->deliverableResource($deliverable));

        $this->assertSame('Entrega Final', $deliverable->name);
        $this->assertSame('Documento final', $deliverable->description);
        $this->assertSame('2025-05-01 12:00:00', $deliverable->due_date?->toDateTimeString());
        $this->assertEqualsCanonicalizing([$fileA->id, $fileB->id], $deliverable->files->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$rubricA->id, $rubricB->id], $deliverable->rubrics->pluck('id')->all());
    }

    public function test_destroy_removes_deliverable(): void
    {
        [$phase] = $this->createPhaseWithPeriod();

        $deliverable = $phase->deliverables()->create([
            'name' => 'Entrega 1',
            'due_date' => '2025-04-15 18:00:00',
        ]);

        $this->deleteJson($this->deliverableRoute($deliverable))
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

        $this->getJson($this->deliverableDropdownRoute($phase))
            ->assertOk()
            ->assertExactJson([
                ['value' => $newer->id, 'label' => 'Entrega 2'],
                ['value' => $older->id, 'label' => 'Entrega 1'],
            ]);

    }

    private function deliverablesRoute(Phase $phase): string
    {
        return "/api/pg/academic-periods/{$phase->period_id}/phases/{$phase->id}/deliverables";
    }

    private function deliverableRoute(Deliverable $deliverable): string
    {
        $phase = $deliverable->phase ?? $deliverable->phase()->with('period')->firstOrFail();

        return $this->deliverablesRoute($phase)."/{$deliverable->id}";
    }

    private function deliverableDropdownRoute(Phase $phase): string
    {
        return $this->deliverablesRoute($phase).'/dropdown';
    }

    /**
     * @return array{0: Phase}
     */
    private function createPhaseWithPeriod(string $name = 'PG I'): array
    {
        $period = AcademicPeriod::create([
            'name' => '2025-1',
            'start_date' => '2025-01-01',
            'end_date' => '2025-06-30',
        ]);

        $phase = $period->phases()->create([
            'name' => $name,
            'start_date' => '2025-01-01',
            'end_date' => '2025-06-30',
        ]);

        return [$phase];
    }
}
