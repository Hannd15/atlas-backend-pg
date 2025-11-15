<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicPeriodState;
use App\Models\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\PgApiResponseHelpers;
use Tests\TestCase;

class AcademicPeriodEndpointsTest extends TestCase
{
    use PgApiResponseHelpers;
    use RefreshDatabase;

    protected bool $ensureActivePhase = false;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2025-01-01 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_index_returns_transformed_academic_periods(): void
    {
        $period = AcademicPeriod::create([
            'name' => '2025-1',
            'start_date' => '2025-01-15',
            'end_date' => '2025-06-30',
        ]);

        $phaseOne = $period->phases()->create([
            'name' => 'Proyecto de grado I',
            'start_date' => '2025-01-15',
            'end_date' => '2025-06-30',
        ]);

        $phaseTwo = $period->phases()->create([
            'name' => 'Proyecto de grado II',
            'start_date' => '2025-01-15',
            'end_date' => '2025-06-30',
        ]);

        $file = File::create([
            'name' => 'propuesta.pdf',
            'extension' => 'pdf',
            'url' => 'https://storage.test/files/propuesta.pdf',
            'disk' => 'public',
            'path' => 'pg/uploads/propuesta.pdf',
        ]);

        $deliverable = $phaseOne->deliverables()->create([
            'name' => 'Entrega 1',
            'description' => 'Documento inicial',
            'due_date' => '2025-02-15 23:59:00',
        ]);
        $deliverable->files()->sync([$file->id]);

        $response = $this->getJson('/api/pg/academic-periods');

        $response->assertOk()
            ->assertExactJson([
                [
                    'id' => $period->id,
                    'name' => $period->name,
                    'state_name' => AcademicPeriodState::NAME_ACTIVO,
                ],
            ]);
    }

    public function test_store_creates_period_with_default_phases(): void
    {
        $payload = [
            'name' => '2026-2',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-15',
        ];

        Carbon::setTestNow('2025-07-01 12:00:00');

        $response = $this->postJson('/api/pg/academic-periods', $payload);

        $period = AcademicPeriod::where('name', '2026-2')->firstOrFail();

        $response->assertCreated()
            ->assertExactJson($this->academicPeriodResource($period));

        $period->refresh();

        $this->assertCount(2, $period->phases);
        $this->assertSame(AcademicPeriodState::NAME_ACTIVO, $period->state->name);
    }

    public function test_show_returns_transformed_period(): void
    {
        $period = AcademicPeriod::create([
            'name' => '2025-2',
            'start_date' => '2025-07-01',
            'end_date' => '2025-12-15',
        ]);

        $period->phases()->create([
            'name' => 'Fase I',
            'start_date' => '2025-07-01',
            'end_date' => '2025-12-15',
        ]);
        $period->phases()->create([
            'name' => 'Fase II',
            'start_date' => '2025-07-01',
            'end_date' => '2025-12-15',
        ]);

        $response = $this->getJson("/api/pg/academic-periods/{$period->id}");

        $response->assertOk()
            ->assertExactJson($this->academicPeriodResource($period->fresh()));
    }

    public function test_update_applies_payload_to_period_and_phases(): void
    {
        $period = AcademicPeriod::create([
            'name' => '2026-1',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
        ]);

        [$phaseOne, $phaseTwo] = [
            $period->phases()->create([
                'name' => 'Phase A',
                'start_date' => '2026-01-01',
                'end_date' => '2026-06-30',
            ]),
            $period->phases()->create([
                'name' => 'Phase B',
                'start_date' => '2026-01-01',
                'end_date' => '2026-06-30',
            ]),
        ];

        $payload = [
            'name' => '2026-2',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-15',
        ];

        $response = $this->putJson("/api/pg/academic-periods/{$period->id}", $payload);

        $period->refresh();
        $phaseOne->refresh();
        $phaseTwo->refresh();

        $response->assertOk()
            ->assertExactJson($this->academicPeriodResource($period));

        $this->assertEquals('2026-2', $period->name);
        $this->assertEquals('Phase A', $phaseOne->name);
        $this->assertEquals('Phase B', $phaseTwo->name);
    }

    public function test_destroy_removes_period(): void
    {
        $period = AcademicPeriod::create([
            'name' => '2027-1',
            'start_date' => '2027-01-01',
            'end_date' => '2027-06-30',
        ]);

        $period->phases()->create([
            'name' => 'Phase 1',
            'start_date' => '2027-01-01',
            'end_date' => '2027-06-30',
        ]);
        $period->phases()->create([
            'name' => 'Phase 2',
            'start_date' => '2027-01-01',
            'end_date' => '2027-06-30',
        ]);

        $this->deleteJson("/api/pg/academic-periods/{$period->id}")
            ->assertOk()
            ->assertExactJson(['message' => 'Academic period deleted successfully']);

        $this->assertDatabaseMissing('academic_periods', ['id' => $period->id]);
    }

    public function test_dropdown_returns_value_label_pairs(): void
    {
        $first = AcademicPeriod::create([
            'name' => '2028-1',
            'start_date' => '2028-01-01',
            'end_date' => '2028-06-30',
        ]);
        $second = AcademicPeriod::create([
            'name' => '2029-1',
            'start_date' => '2029-01-01',
            'end_date' => '2029-06-30',
        ]);

        $this->getJson('/api/pg/academic-periods/dropdown')
            ->assertOk()
            ->assertExactJson([
                ['value' => $second->id, 'label' => '2029-1'],
                ['value' => $first->id, 'label' => '2028-1'],
            ]);
    }

    public function test_state_dropdown_returns_value_label_pairs(): void
    {
        $active = AcademicPeriodState::ensureActive();
        $finished = AcademicPeriodState::ensureFinished();

        $this->getJson('/api/pg/academic-period-states/dropdown')
            ->assertOk()
            ->assertExactJson([
                ['value' => $active->id, 'label' => $active->name],
                ['value' => $finished->id, 'label' => $finished->name],
            ]);
    }
}
