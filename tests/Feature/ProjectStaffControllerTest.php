<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Project;
use App\Models\ProjectPosition;
use App\Models\ProjectStaff;
use App\Models\User;
use App\Services\ApprovalRequestService;
use Tests\TestCase;

class ProjectStaffControllerTest extends TestCase
{
    protected bool $seed = false;

    public function test_index_returns_staff_assignments(): void
    {
        $project = Project::factory()->create();
        $position = ProjectPosition::create(['name' => 'Director']);
        $user = User::factory()->create(['name' => 'Alice Example']);

        ProjectStaff::create([
            'project_id' => $project->id,
            'project_position_id' => $position->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $this->getJson("/api/pg/projects/{$project->id}/staff", $this->defaultHeaders)
            ->assertOk()
            ->assertExactJson([
                [
                    'position' => 'Director',
                    'user_name' => 'Alice Example',
                ],
            ]);
    }

    public function test_store_creates_pending_approval_request(): void
    {
        $requester = User::factory()->create();
        $this->mockAtlasUser(['id' => $requester->id]);

        $project = Project::factory()->create([
            'title' => 'Proyecto Demo',
            'description' => 'Descripción del proyecto',
        ]);
        $position = ProjectPosition::create(['name' => 'Director']);
        $user = User::factory()->create(['name' => 'Bob Example']);

        $response = $this->postJson(
            "/api/pg/projects/{$project->id}/project-positions/{$position->id}/users/{$user->id}/staff",
            [],
            $this->defaultHeaders
        )
            ->assertStatus(202)
            ->assertJson([
                'status' => ApprovalRequest::STATUS_PENDING,
                'position' => 'Director',
                'user_name' => 'Bob Example',
                'pending_decision' => true,
            ])
            ->assertJsonStructure(['approval_request_id']);

        $this->assertDatabaseMissing('project_staff', [
            'project_id' => $project->id,
            'project_position_id' => $position->id,
            'user_id' => $user->id,
        ]);

        $approvalRequest = ApprovalRequest::first();
        $this->assertNotNull($approvalRequest);
        $this->assertSame($requester->id, $approvalRequest->requested_by);
        $this->assertSame('project.staff.assign', $approvalRequest->action_key);
        $this->assertSame(
            [
                'project_id' => $project->id,
                'project_position_id' => $position->id,
                'user_id' => $user->id,
            ],
            $approvalRequest->action_payload
        );
        $this->assertSame('Descripción del proyecto', $approvalRequest->description);
        $this->assertDatabaseHas('approval_request_recipients', [
            'approval_request_id' => $approvalRequest->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_store_returns_existing_assignment_when_already_assigned(): void
    {
        $project = Project::factory()->create();
        $position = ProjectPosition::create(['name' => 'Director']);
        $user = User::factory()->create(['name' => 'Bob Example']);

        ProjectStaff::create([
            'project_id' => $project->id,
            'project_position_id' => $position->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $this->postJson(
            "/api/pg/projects/{$project->id}/project-positions/{$position->id}/users/{$user->id}/staff",
            [],
            $this->defaultHeaders
        )
            ->assertOk()
            ->assertExactJson([
                'position' => 'Director',
                'user_name' => 'Bob Example',
            ]);

        $this->assertDatabaseCount('approval_requests', 0);
    }

    public function test_approval_request_action_assigns_staff_after_user_approval(): void
    {
        $requester = User::factory()->create();
        $this->mockAtlasUser(['id' => $requester->id]);

        $project = Project::factory()->create();
        $position = ProjectPosition::create(['name' => 'Director']);
        $user = User::factory()->create();

        $response = $this->postJson(
            "/api/pg/projects/{$project->id}/project-positions/{$position->id}/users/{$user->id}/staff",
            [],
            $this->defaultHeaders
        )->assertStatus(202);

        $approvalRequest = ApprovalRequest::findOrFail($response->json('approval_request_id'));

        /** @var ApprovalRequestService $service */
        $service = app(ApprovalRequestService::class);
        $service->recordDecision($approvalRequest, $user->id, ApprovalRequest::DECISION_APPROVED);

        $this->assertDatabaseHas('project_staff', [
            'project_id' => $project->id,
            'project_position_id' => $position->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
    }

    public function test_destroy_removes_assignment(): void
    {
        $project = Project::factory()->create();
        $position = ProjectPosition::create(['name' => 'Director']);
        $user = User::factory()->create(['name' => 'Charlie Example']);

        ProjectStaff::create([
            'project_id' => $project->id,
            'project_position_id' => $position->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $this->deleteJson(
            "/api/pg/projects/{$project->id}/project-positions/{$position->id}/users/{$user->id}/staff",
            [],
            $this->defaultHeaders
        )->assertNoContent();

        $this->assertDatabaseMissing('project_staff', [
            'project_id' => $project->id,
            'project_position_id' => $position->id,
            'user_id' => $user->id,
        ]);
    }
}
