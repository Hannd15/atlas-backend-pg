<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\AcademicPeriod;
use App\Models\Deliverable;
use App\Models\Phase;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class AtlasEndpointContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_academic_periods_index_returns_summary_schema(): void
    {
        $response = $this->getJson('/api/pg/academic-periods');

        $response->assertOk()->assertJson(fn (AssertableJson $json) => $json
            ->each(fn (AssertableJson $item) => $item
                ->hasAll(['id', 'name', 'state_id', 'state_name', 'state_description'])
                ->whereType('id', 'integer')
                ->whereType('name', 'string')
                ->whereType('state_id', ['integer', 'null'])
                ->whereType('state_name', ['string', 'null'])
                ->whereType('state_description', ['string', 'null'])
                ->etc()
            )
        );
    }

    public function test_academic_period_show_returns_full_structure(): void
    {
        $period = AcademicPeriod::query()->firstOrFail();

        $response = $this->getJson("/api/pg/academic-periods/{$period->id}");

        $response->assertOk()->assertJson(fn (AssertableJson $json) => $json
            ->hasAll(['id', 'name', 'start_date', 'end_date', 'state_id', 'state_name', 'state_description', 'created_at', 'updated_at'])
            ->whereType('state_id', ['integer', 'null'])
            ->whereType('state_name', ['string', 'null'])
            ->whereType('state_description', ['string', 'null'])
            ->etc()
        );
    }

    public function test_phases_index_returns_expected_payload(): void
    {
        $phase = Phase::with('period')->firstOrFail();

        $response = $this->getJson("/api/pg/academic-periods/{$phase->period_id}/phases");

        $response->assertOk()->assertJson(fn (AssertableJson $json) => $json
            ->each(fn (AssertableJson $item) => $item
                ->hasAll(['id', 'name'])
                ->whereType('id', 'integer')
                ->whereType('name', 'string')
                ->etc()
            )
        );
    }

    public function test_deliverable_show_returns_nested_metadata(): void
    {
        $deliverable = Deliverable::with('phase.period')->firstOrFail();
        $phase = $deliverable->phase;
        $period = $phase?->period;

        $this->assertNotNull($phase, 'Deliverable missing phase relationship.');
        $this->assertNotNull($period, 'Deliverable phase missing period relationship.');

        $response = $this->getJson("/api/pg/academic-periods/{$period->id}/phases/{$phase->id}/deliverables/{$deliverable->id}");

        $response->assertOk()->assertJson(fn (AssertableJson $json) => $json
            ->hasAll(['id', 'phase_id', 'name', 'description', 'due_date', 'created_at', 'updated_at'])
            ->missing('phase')
            ->missing('files')
            ->missing('rubrics')
            ->missing('file_ids')
            ->missing('rubric_ids')
            ->missing('rubric_names')
            ->etc()
        );
    }

    public function test_deliverables_index_returns_minimal_payload(): void
    {
        $deliverable = Deliverable::with('phase.period')->firstOrFail();
        $phase = $deliverable->phase;
        $period = $phase?->period;

        $this->assertNotNull($phase, 'Deliverable missing phase relationship.');
        $this->assertNotNull($period, 'Deliverable phase missing period relationship.');

        $response = $this->getJson("/api/pg/academic-periods/{$period->id}/phases/{$phase->id}/deliverables");

        $response->assertOk()->assertJson(fn (AssertableJson $json) => $json
            ->each(fn (AssertableJson $item) => $item
                ->hasAll(['id', 'name', 'due_date'])
                ->whereType('id', 'integer')
                ->whereType('name', 'string')
                ->whereType('due_date', ['string', 'null'])
                ->missing('description')
                ->missing('phase_id')
                ->etc()
            )
        );
    }

    public function test_projects_index_returns_expected_fields(): void
    {
        $response = $this->getJson('/api/pg/projects');

        $response->assertOk()->assertJson(fn (AssertableJson $json) => $json
            ->each(fn (AssertableJson $item) => $item
                ->hasAll(['id', 'title', 'status', 'member_names', 'created_at', 'updated_at'])
                ->etc()
            )
        );
    }

    public function test_project_show_includes_nested_collections(): void
    {
        $project = Project::whereHas('deliverables')->first()
            ?? Project::query()->firstOrFail();

        $response = $this->getJson("/api/pg/projects/{$project->id}");

        $response->assertOk()->assertJson(fn (AssertableJson $json) => $json
            ->hasAll(['id', 'title', 'status', 'proposal_id', 'thematic_line_name', 'member_names', 'created_at', 'updated_at'])
            ->has('staff', fn (AssertableJson $staff) => $staff->each(fn (AssertableJson $member) => $member
                ->hasAll(['user_name', 'position_name'])
                ->etc()
            ))
            ->has('meetings')
            ->has('deliverables')
            ->etc()
        );

        $payload = $response->json();
        $deliverables = $payload['deliverables'] ?? [];

        if (is_array($deliverables) && $deliverables !== []) {
            foreach ($deliverables as $deliverablePayload) {
                $this->assertArrayHasKey('id', $deliverablePayload);
                $this->assertArrayHasKey('name', $deliverablePayload);
                $this->assertArrayHasKey('due_date', $deliverablePayload);
                $this->assertArrayHasKey('grading', $deliverablePayload);
                $this->assertArrayHasKey('state', $deliverablePayload);
            }
        }
    }

    public function test_submissions_index_returns_expected_structure(): void
    {
        $response = $this->getJson('/api/pg/submissions');

        $response->assertOk()->assertJson(fn (AssertableJson $json) => $json
            ->each(fn (AssertableJson $item) => $item
                ->hasAll(['id', 'deliverable_id', 'project_id', 'submission_date', 'created_at', 'updated_at'])
                ->has('deliverable')
                ->has('project')
                ->has('files')
                ->has('evaluations')
                ->has('file_ids')
                ->etc()
            )
        );
    }

    public function test_rubrics_dropdown_returns_value_label_pairs(): void
    {
        $response = $this->getJson('/api/pg/rubrics/dropdown');

        $response->assertOk()->assertJson(fn (AssertableJson $json) => $json
            ->each(fn (AssertableJson $item) => $item
                ->hasAll(['value', 'label'])
                ->etc()
            )
        );
    }

    public function test_users_index_returns_remote_fields(): void
    {
        $response = $this->getJson('/api/pg/users');

        $response->assertOk()->assertJson(fn (AssertableJson $json) => $json
            ->each(fn (AssertableJson $item) => $item
                ->hasAll(['id', 'name', 'email', 'proposal_names', 'project_position_eligibility_names', 'created_at', 'updated_at'])
                ->etc()
            )
        );
    }

    public function test_method_not_allowed_returns_explanation(): void
    {
        $period = AcademicPeriod::query()->firstOrFail();

        $response = $this->postJson("/api/pg/academic-periods/{$period->id}");

        $response->assertStatus(405)
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('message')
                ->whereType('message', 'string')
                ->etc()
            )
            ->assertHeader('Allow');
    }
}
