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
        $state = AcademicPeriodState::create([
            'name' => 'Active',
            'description' => 'Currently running.',
        ]);

        $period = AcademicPeriod::create([
            'name' => '2025-1',
            'start_date' => '2025-01-15',
            'end_date' => '2025-06-30',
            'state_id' => $state->id,
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
                $this->academicPeriodResource($period->fresh()),
            ]);
    }

    public function test_store_creates_period_with_default_phases(): void
    {
        AcademicPeriodState::create([
            'name' => 'Draft',
            'description' => 'Pending start.',
        ]);

        $payload = [
            'name' => '2026-1',
            'start_date' => '2026-01-10',
            'end_date' => '2026-06-25',
        ];

        $response = $this->postJson('/api/pg/academic-periods', $payload);

        $period = AcademicPeriod::first();

        $response->assertCreated()
            ->assertExactJson($this->academicPeriodResource($period));

        $this->assertCount(2, $period->phases);
        $this->assertEquals('Proyecto de grado I', $period->phases[0]->name);
        $this->assertEquals('Proyecto de grado II', $period->phases[1]->name);
    }

    public function test_show_returns_transformed_period(): void
    {
        $state = AcademicPeriodState::create([
            'name' => 'Active',
            'description' => 'Currently running.',
        ]);

        $period = AcademicPeriod::create([
            'name' => '2025-2',
            'start_date' => '2025-07-01',
            'end_date' => '2025-12-15',
            'state_id' => $state->id,
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
        $draft = AcademicPeriodState::create([
            'name' => 'Draft',
            'description' => 'Pending start.',
        ]);

        $active = AcademicPeriodState::create([
            'name' => 'Active',
            'description' => 'Running.',
        ]);

        $period = AcademicPeriod::create([
            'name' => '2026-1',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
            'state_id' => $draft->id,
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

        $file = File::create([
            'name' => 'entrega.pdf',
            'extension' => 'pdf',
            'url' => 'https://storage.test/files/entrega.pdf',
            'disk' => 'public',
            'path' => 'pg/uploads/entrega.pdf',
        ]);

        $payload = [
            'name' => '2026-2',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-15',
            'state_id' => $active->id,
            'phases' => [
                'phase_one' => [
                    'name' => 'First Phase',
                    'deliverables' => [
                        [
                            'name' => 'Entrega Final',
                            'description' => 'Documento final',
                            'due_date' => '2026-09-30 23:59:00',
                            'file_ids' => [$file->id],
                        ],
                    ],
                ],
                'phase_two' => [
                    'name' => 'Second Phase',
                    'deliverables' => [],
                ],
            ],
        ];

        $response = $this->putJson("/api/pg/academic-periods/{$period->id}", $payload);

        $period->refresh();

        $response->assertOk()
            ->assertExactJson($this->academicPeriodResource($period));

        $this->assertEquals('2026-2', $period->name);
        $this->assertEquals('First Phase', $phaseOne->fresh()->name);
        $this->assertEquals('Second Phase', $phaseTwo->fresh()->name);
        $this->assertCount(1, $phaseOne->deliverables);
    }

    public function test_destroy_removes_period(): void
    {
        $state = AcademicPeriodState::create([
            'name' => 'Draft',
            'description' => 'Pending',
        ]);

        $period = AcademicPeriod::create([
            'name' => '2027-1',
            'start_date' => '2027-01-01',
            'end_date' => '2027-06-30',
            'state_id' => $state->id,
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
        $state = AcademicPeriodState::create([
            'name' => 'Draft',
            'description' => 'Pending',
        ]);

        $first = AcademicPeriod::create([
            'name' => '2028-1',
            'start_date' => '2028-01-01',
            'end_date' => '2028-06-30',
            'state_id' => $state->id,
        ]);
        $second = AcademicPeriod::create([
            'name' => '2029-1',
            'start_date' => '2029-01-01',
            'end_date' => '2029-06-30',
            'state_id' => $state->id,
        ]);

        $this->getJson('/api/pg/academic-periods/dropdown')
            ->assertOk()
            ->assertExactJson([
                ['value' => $second->id, 'label' => '2029-1'],
                ['value' => $first->id, 'label' => '2028-1'],
            ]);
    }
}
