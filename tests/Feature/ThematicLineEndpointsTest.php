<?php

namespace Tests\Feature;

use App\Models\Rubric;
use App\Models\ThematicLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThematicLineEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_expected_payload(): void
    {
        $line = ThematicLine::create([
            'name' => 'Innovación',
            'description' => 'Line focused on research topics',
        ]);

        $rubric = Rubric::create([
            'name' => 'Pertinencia',
            'description' => 'Evalúa pertinencia',
            'min_value' => 0,
            'max_value' => 5,
        ]);
        $line->rubrics()->attach($rubric);

        $response = $this->getJson('/api/pg/thematic-lines');

        $expected = ThematicLine::with('rubrics')->orderByDesc('updated_at')->get()
            ->map(fn (ThematicLine $thematicLine) => [
                'id' => $thematicLine->id,
                'name' => $thematicLine->name,
                'description' => $thematicLine->description,
                'rubric_names' => $thematicLine->rubrics->pluck('name')->implode(', '),
                'rubric_ids' => $thematicLine->rubrics->pluck('id')->values()->all(),
                'created_at' => optional($thematicLine->created_at)->toDateTimeString(),
                'updated_at' => optional($thematicLine->updated_at)->toDateTimeString(),
            ])->values()->all();

        $response->assertOk()->assertExactJson($expected);
    }

    public function test_show_returns_expected_payload(): void
    {
        $line = ThematicLine::create([
            'name' => 'Sostenibilidad',
            'description' => 'Line focused on sustainability',
        ]);

        $rubric = Rubric::create([
            'name' => 'Sustentabilidad',
            'description' => 'Evalúa sostenibilidad',
            'min_value' => 0,
            'max_value' => 5,
        ]);
        $line->rubrics()->attach($rubric);

        $response = $this->getJson("/api/pg/thematic-lines/{$line->id}");

        $line->load('rubrics');

        $response->assertOk()->assertExactJson([
            'id' => $line->id,
            'name' => $line->name,
            'description' => $line->description,
            'rubric_ids' => $line->rubrics->pluck('id')->values()->all(),
            'rubric_names' => $line->rubrics->pluck('name')->implode(', '),
            'created_at' => optional($line->created_at)->toDateTimeString(),
            'updated_at' => optional($line->updated_at)->toDateTimeString(),
        ]);
    }

    public function test_store_creates_thematic_line_with_rubrics(): void
    {
        $rubricA = Rubric::create([
            'name' => 'Calidad',
            'description' => 'Evalúa calidad',
            'min_value' => 0,
            'max_value' => 10,
        ]);
        $rubricB = Rubric::create([
            'name' => 'Originalidad',
            'description' => 'Evalúa originalidad',
            'min_value' => 0,
            'max_value' => 10,
        ]);

        $payload = [
            'name' => 'Transformación Digital',
            'description' => 'Uso de tecnología para mejorar procesos',
            'rubric_ids' => [$rubricA->id, $rubricB->id],
        ];

        $response = $this->postJson('/api/pg/thematic-lines', $payload);

        $line = ThematicLine::with('rubrics')->firstOrFail();

        $response->assertCreated()->assertExactJson([
            'id' => $line->id,
            'name' => $line->name,
            'description' => $line->description,
            'rubric_ids' => $line->rubrics->pluck('id')->values()->all(),
            'rubric_names' => $line->rubrics->pluck('name')->implode(', '),
            'created_at' => optional($line->created_at)->toDateTimeString(),
            'updated_at' => optional($line->updated_at)->toDateTimeString(),
        ]);

        $this->assertDatabaseHas('rubric_thematic_lines', [
            'thematic_line_id' => $line->id,
            'rubric_id' => $rubricA->id,
        ]);
        $this->assertDatabaseHas('rubric_thematic_lines', [
            'thematic_line_id' => $line->id,
            'rubric_id' => $rubricB->id,
        ]);
    }

    public function test_update_replaces_rubrics_when_array_provided(): void
    {
        $line = ThematicLine::create([
            'name' => 'Gestión de proyectos',
            'description' => 'Línea enfocada en gestión',
        ]);

        $initialRubric = Rubric::create([
            'name' => 'Planeación',
            'description' => 'Evalúa planeación',
            'min_value' => 0,
            'max_value' => 5,
        ]);
        $line->rubrics()->attach($initialRubric);

        $rubricA = Rubric::create([
            'name' => 'Ejecución',
            'description' => 'Evalúa ejecución',
            'min_value' => 0,
            'max_value' => 5,
        ]);
        $rubricB = Rubric::create([
            'name' => 'Seguimiento',
            'description' => 'Evalúa seguimiento',
            'min_value' => 0,
            'max_value' => 5,
        ]);

        $payload = [
            'name' => 'Gestión actualizada',
            'rubric_ids' => [$rubricA->id, $rubricB->id],
        ];

        $response = $this->putJson("/api/pg/thematic-lines/{$line->id}", $payload);

        $line->refresh()->load('rubrics');

        $response->assertOk()->assertExactJson([
            'id' => $line->id,
            'name' => 'Gestión actualizada',
            'description' => $line->description,
            'rubric_ids' => $line->rubrics->pluck('id')->values()->all(),
            'rubric_names' => $line->rubrics->pluck('name')->implode(', '),
            'created_at' => optional($line->created_at)->toDateTimeString(),
            'updated_at' => optional($line->updated_at)->toDateTimeString(),
        ]);

        $this->assertEqualsCanonicalizing([$rubricA->id, $rubricB->id], $line->rubrics->pluck('id')->all());
    }

    public function test_update_ignores_rubric_sync_when_null(): void
    {
        $line = ThematicLine::create([
            'name' => 'Realidad aumentada',
            'description' => 'Línea de RA',
        ]);

        $rubric = Rubric::create([
            'name' => 'Interfaz',
            'description' => 'Evalúa interfaz',
            'min_value' => 0,
            'max_value' => 10,
        ]);
        $line->rubrics()->attach($rubric);

        $payload = [
            'description' => 'Actualización',
            'rubric_ids' => null,
        ];

        $response = $this->putJson("/api/pg/thematic-lines/{$line->id}", $payload);

        $line->refresh()->load('rubrics');

        $response->assertOk();
        $this->assertEqualsCanonicalizing([$rubric->id], $line->rubrics->pluck('id')->all());
    }

    public function test_destroy_deletes_thematic_line(): void
    {
        $line = ThematicLine::create([
            'name' => 'Biomedicina',
            'description' => 'Línea biomédica',
        ]);

        $this->deleteJson("/api/pg/thematic-lines/{$line->id}")
            ->assertOk()
            ->assertExactJson(['message' => 'Thematic line deleted successfully']);

        $this->assertDatabaseMissing('thematic_lines', ['id' => $line->id]);
    }

    public function test_dropdown_returns_value_label_pairs(): void
    {
        $lineA = ThematicLine::create(['name' => 'Energía']);
        $lineB = ThematicLine::create(['name' => 'Big Data']);

        $this->getJson('/api/pg/thematic-lines/dropdown')
            ->assertOk()
            ->assertExactJson([
                ['value' => $lineA->id, 'label' => 'Energía'],
                ['value' => $lineB->id, 'label' => 'Big Data'],
            ]);
    }
}
