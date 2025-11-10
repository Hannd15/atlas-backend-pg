<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicPeriodState;
use App\Models\Phase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\PgApiResponseHelpers;
use Tests\TestCase;

class PhaseEndpointsTest extends TestCase
{
    use PgApiResponseHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2025-03-01 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_index_returns_expected_payload(): void
    {
        $period = $this->createPeriod();

        $phase = $period->phases()->create([
            'name' => 'Phase One',
            'start_date' => '2025-03-01',
            'end_date' => '2025-08-30',
        ]);

        $phase->deliverables()->create([
            'name' => 'Entrega 1',
            'description' => 'Descripcion',
            'due_date' => '2025-04-15 18:00:00',
        ]);

        $period->phases()->create([
            'name' => 'Phase Two',
            'start_date' => '2025-03-01',
            'end_date' => '2025-08-30',
        ]);

        $response = $this->getJson('/api/pg/phases');

        $phases = Phase::with('period', 'deliverables')->orderBy('updated_at', 'desc')->get();

        $response->assertOk()
            ->assertExactJson($this->phaseIndexArray($phases));
    }

    public function test_show_returns_phase_with_relation_ids(): void
    {
        $period = $this->createPeriod();

        $phase = $period->phases()->create([
            'name' => 'Phase One',
            'start_date' => '2025-03-01',
            'end_date' => '2025-08-30',
        ]);

        $phase->deliverables()->create([
            'name' => 'Entrega 1',
            'description' => 'Descripcion',
            'due_date' => '2025-04-15 18:00:00',
        ]);

        $response = $this->getJson("/api/pg/phases/{$phase->id}");

        $phase->load('period', 'deliverables');
        $phase->period_id = [$period->id];
        $phase->deliverable_ids = $phase->deliverables->pluck('id');

        $response->assertOk()
            ->assertExactJson($phase->toArray());
    }

    public function test_update_applies_new_period_and_name(): void
    {
        $period = $this->createPeriod();

        $phase = $period->phases()->create([
            'name' => 'Phase One',
            'start_date' => '2025-03-01',
            'end_date' => '2025-08-30',
        ]);

        $otherPeriod = AcademicPeriod::create([
            'name' => '2025-2',
            'start_date' => '2025-09-01',
            'end_date' => '2026-01-31',
            'state_id' => AcademicPeriodState::create([
                'name' => 'Next',
                'description' => 'Next period',
            ])->id,
        ]);

        $payload = [
            'name' => 'Updated Phase',
            'period_id' => $otherPeriod->id,
        ];

        $response = $this->putJson("/api/pg/phases/{$phase->id}", $payload);

        $phase->refresh();
        $phase->load('period');
        $expected = $phase->toArray();

        $response->assertOk()->assertExactJson($expected);

        $this->assertEquals('Updated Phase', $phase->name);
        $this->assertEquals($otherPeriod->id, $phase->period_id);
        $this->assertSame('2025-09-01', $phase->start_date->toDateString());
        $this->assertSame('2026-01-31', $phase->end_date->toDateString());
    }

    public function test_destroy_removes_phase(): void
    {
        $period = $this->createPeriod();

        $phase = $period->phases()->create([
            'name' => 'Phase One',
            'start_date' => '2025-03-01',
            'end_date' => '2025-08-30',
        ]);

        $this->deleteJson("/api/pg/phases/{$phase->id}")
            ->assertOk()
            ->assertExactJson(['message' => 'Phase deleted successfully']);

        $this->assertDatabaseMissing('phases', ['id' => $phase->id]);
    }

    public function test_dropdown_returns_value_label_pairs(): void
    {
        $period = $this->createPeriod();

        $first = $period->phases()->create([
            'name' => 'Phase One',
            'start_date' => '2025-03-01',
            'end_date' => '2025-08-30',
        ]);
        $second = $period->phases()->create([
            'name' => 'Phase Two',
            'start_date' => '2025-03-01',
            'end_date' => '2025-08-30',
        ]);

        $this->getJson('/api/pg/phases/dropdown')
            ->assertOk()
            ->assertExactJson([
                ['value' => $first->id, 'label' => 'Phase One'],
                ['value' => $second->id, 'label' => 'Phase Two'],
            ]);
    }

    private function createPeriod(): AcademicPeriod
    {
        $state = AcademicPeriodState::first() ?? AcademicPeriodState::create([
            'name' => 'Active',
            'description' => 'State',
        ]);

        return AcademicPeriod::create([
            'name' => '2025-1',
            'start_date' => '2025-01-01',
            'end_date' => '2025-06-30',
            'state_id' => $state->id,
        ]);
    }
}
