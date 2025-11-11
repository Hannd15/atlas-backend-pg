<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicPeriodState;
use App\Models\Deliverable;
use App\Models\Rubric;
use App\Models\ThematicLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RubricEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2025-03-01 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_index_returns_expected_payload(): void
    {
        [$deliverable] = $this->createDeliverableFixture();

        $line = ThematicLine::create([
            'name' => 'Tecnologías emergentes',
        ]);

        $rubric = Rubric::create([
            'name' => 'Evaluación general',
            'description' => 'Evalúa elementos generales',
            'min_value' => 0,
            'max_value' => 10,
        ]);
        $rubric->thematicLines()->attach($line);
        $rubric->deliverables()->attach($deliverable);

        $response = $this->getJson('/api/pg/rubrics');

        $rubrics = Rubric::with('thematicLines', 'deliverables')->orderByDesc('updated_at')->get();

        $expected = $rubrics->map(fn (Rubric $item) => [
            'id' => $item->id,
            'name' => $item->name,
            'description' => $item->description,
            'min_value' => $item->min_value,
            'max_value' => $item->max_value,
            'thematic_line_names' => $item->thematicLines->pluck('name')->implode(', '),
            'thematic_line_ids' => $item->thematicLines->pluck('id')->values()->all(),
            'deliverable_names' => $item->deliverables->pluck('name')->implode(', '),
            'deliverable_ids' => $item->deliverables->pluck('id')->values()->all(),
            'created_at' => optional($item->created_at)->toDateTimeString(),
            'updated_at' => optional($item->updated_at)->toDateTimeString(),
        ])->values()->all();

        $response->assertOk()->assertExactJson($expected);
    }

    public function test_show_returns_expected_payload(): void
    {
        [$deliverable] = $this->createDeliverableFixture();

        $line = ThematicLine::create(['name' => 'Inteligencia Artificial']);

        $rubric = Rubric::create([
            'name' => 'Precisión',
            'description' => 'Evalúa precisión',
            'min_value' => 0,
            'max_value' => 10,
        ]);
        $rubric->thematicLines()->attach($line);
        $rubric->deliverables()->attach($deliverable);

        $response = $this->getJson("/api/pg/rubrics/{$rubric->id}");

        $rubric->load('thematicLines', 'deliverables');

        $response->assertOk()->assertExactJson([
            'id' => $rubric->id,
            'name' => $rubric->name,
            'description' => $rubric->description,
            'min_value' => $rubric->min_value,
            'max_value' => $rubric->max_value,
            'thematic_line_ids' => $rubric->thematicLines->pluck('id')->values()->all(),
            'thematic_line_names' => $rubric->thematicLines->pluck('name')->implode(', '),
            'deliverable_ids' => $rubric->deliverables->pluck('id')->values()->all(),
            'deliverable_names' => $rubric->deliverables->pluck('name')->implode(', '),
            'created_at' => optional($rubric->created_at)->toDateTimeString(),
            'updated_at' => optional($rubric->updated_at)->toDateTimeString(),
        ]);
    }

    public function test_store_creates_rubric_with_relationships(): void
    {
        [$deliverable] = $this->createDeliverableFixture();
        $line = ThematicLine::create(['name' => 'Analítica de datos']);

        $payload = [
            'name' => 'Calidad del entregable',
            'description' => 'Evalúa calidad del entregable',
            'min_value' => 0,
            'max_value' => 5,
            'thematic_line_ids' => [$line->id],
            'deliverable_ids' => [$deliverable->id],
        ];

        $response = $this->postJson('/api/pg/rubrics', $payload);

        $rubric = Rubric::with('thematicLines', 'deliverables')->firstOrFail();

        $response->assertCreated()->assertExactJson([
            'id' => $rubric->id,
            'name' => $rubric->name,
            'description' => $rubric->description,
            'min_value' => $rubric->min_value,
            'max_value' => $rubric->max_value,
            'thematic_line_ids' => $rubric->thematicLines->pluck('id')->values()->all(),
            'thematic_line_names' => $rubric->thematicLines->pluck('name')->implode(', '),
            'deliverable_ids' => $rubric->deliverables->pluck('id')->values()->all(),
            'deliverable_names' => $rubric->deliverables->pluck('name')->implode(', '),
            'created_at' => optional($rubric->created_at)->toDateTimeString(),
            'updated_at' => optional($rubric->updated_at)->toDateTimeString(),
        ]);

        $this->assertDatabaseHas('rubric_thematic_lines', [
            'rubric_id' => $rubric->id,
            'thematic_line_id' => $line->id,
        ]);
        $this->assertDatabaseHas('rubric_deliverables', [
            'rubric_id' => $rubric->id,
            'deliverable_id' => $deliverable->id,
        ]);
    }

    public function test_update_replaces_relationships_when_arrays_provided(): void
    {
        [$deliverable] = $this->createDeliverableFixture();
        [$otherDeliverable] = $this->createDeliverableFixture(name: 'Entrega 2');

        $line = ThematicLine::create(['name' => 'Realidad virtual']);
        $otherLine = ThematicLine::create(['name' => 'Ciberseguridad']);

        $rubric = Rubric::create([
            'name' => 'Evaluación original',
            'description' => 'Evalúa originalidad',
            'min_value' => 0,
            'max_value' => 5,
        ]);
        $rubric->thematicLines()->attach($line);
        $rubric->deliverables()->attach($deliverable);

        $payload = [
            'name' => 'Evaluación actualizada',
            'thematic_line_ids' => [$otherLine->id],
            'deliverable_ids' => [$otherDeliverable->id],
        ];

        $response = $this->putJson("/api/pg/rubrics/{$rubric->id}", $payload);

        $rubric->refresh()->load('thematicLines', 'deliverables');

        $response->assertOk()->assertExactJson([
            'id' => $rubric->id,
            'name' => 'Evaluación actualizada',
            'description' => $rubric->description,
            'min_value' => $rubric->min_value,
            'max_value' => $rubric->max_value,
            'thematic_line_ids' => $rubric->thematicLines->pluck('id')->values()->all(),
            'thematic_line_names' => $rubric->thematicLines->pluck('name')->implode(', '),
            'deliverable_ids' => $rubric->deliverables->pluck('id')->values()->all(),
            'deliverable_names' => $rubric->deliverables->pluck('name')->implode(', '),
            'created_at' => optional($rubric->created_at)->toDateTimeString(),
            'updated_at' => optional($rubric->updated_at)->toDateTimeString(),
        ]);

        $this->assertEqualsCanonicalizing([$otherLine->id], $rubric->thematicLines->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$otherDeliverable->id], $rubric->deliverables->pluck('id')->all());
    }

    public function test_update_ignores_null_arrays(): void
    {
        [$deliverable] = $this->createDeliverableFixture();
        $line = ThematicLine::create(['name' => 'Seguridad de la información']);

        $rubric = Rubric::create([
            'name' => 'Seguridad',
            'description' => 'Evalúa seguridad',
            'min_value' => 0,
            'max_value' => 5,
        ]);
        $rubric->thematicLines()->attach($line);
        $rubric->deliverables()->attach($deliverable);

        $payload = [
            'description' => 'Evaluación mejorada',
            'thematic_line_ids' => null,
            'deliverable_ids' => null,
        ];

        $response = $this->putJson("/api/pg/rubrics/{$rubric->id}", $payload);

        $rubric->refresh()->load('thematicLines', 'deliverables');

        $response->assertOk();
        $this->assertEqualsCanonicalizing([$line->id], $rubric->thematicLines->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$deliverable->id], $rubric->deliverables->pluck('id')->all());
    }

    public function test_destroy_deletes_rubric(): void
    {
        $rubric = Rubric::create([
            'name' => 'Temporal',
            'description' => 'Temporal',
            'min_value' => 0,
            'max_value' => 5,
        ]);

        $this->deleteJson("/api/pg/rubrics/{$rubric->id}")
            ->assertOk()
            ->assertExactJson(['message' => 'Rubric deleted successfully']);

        $this->assertDatabaseMissing('rubrics', ['id' => $rubric->id]);
    }

    public function test_dropdown_returns_value_label_pairs(): void
    {
        $rubricA = Rubric::create(['name' => 'Impacto', 'min_value' => 0, 'max_value' => 5]);
        $rubricB = Rubric::create(['name' => 'Viabilidad', 'min_value' => 0, 'max_value' => 5]);

        $this->getJson('/api/pg/rubrics/dropdown')
            ->assertOk()
            ->assertExactJson([
                ['value' => $rubricA->id, 'label' => 'Impacto'],
                ['value' => $rubricB->id, 'label' => 'Viabilidad'],
            ]);
    }

    /**
     * @return array{0: Deliverable}
     */
    private function createDeliverableFixture(string $name = 'Entrega 1'): array
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
            'name' => 'PG I',
            'start_date' => '2025-01-01',
            'end_date' => '2025-06-30',
        ]);

        $deliverable = $phase->deliverables()->create([
            'name' => $name,
            'description' => 'Documento',
            'due_date' => '2025-04-15 18:00:00',
        ]);

        return [$deliverable];
    }
}
