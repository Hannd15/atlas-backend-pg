<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\User;
use App\Services\ApprovalRequestActionRunner;
use App\Services\ApprovalRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectGroupEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = false;

    public function test_index_returns_expected_payload(): void
    {
        $project = Project::factory()->create([
            'title' => 'Sistema de gestión',
        ]);

        $group = ProjectGroup::create([
            'project_id' => $project->id,
        ]);

        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);
        $group->users()->sync([$alice->id, $bob->id]);

        $response = $this->getJson('/api/pg/project-groups');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'project_id' => $project->id,
                'project_name' => 'Sistema de gestión',
                'member_user_ids' => [$alice->id, $bob->id],
                'member_user_names' => 'Alice, Bob',
            ]);

        $payload = $response->json();
        $this->assertIsArray($payload);
        $this->assertNotEmpty($payload);
        $this->assertArrayNotHasKey('name', $payload[0]);
    }

    public function test_store_enqueues_member_requests_for_each_user(): void
    {
        $project = Project::factory()->create([
            'title' => 'Proyecto académico',
        ]);

        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);

        $payload = [
            'project_id' => $project->id,
            'member_user_ids' => [$alice->id, $bob->id],
        ];

        $response = $this->postJson('/api/pg/project-groups', $payload);

        $group = ProjectGroup::with('project', 'members')
            ->where('project_id', $project->id)
            ->latest('id')
            ->firstOrFail();

        $response->assertCreated();

        $this->assertSame($group->id, $response->json('id'));
        $this->assertSame($project->id, $response->json('project_id'));
        $this->assertSame($project->title, $response->json('project_name'));
        $this->assertSame([], $response->json('member_user_ids'));

        $requests = ApprovalRequest::with('recipients')
            ->where('action_key', 'project_group.add_member')
            ->get();
        $this->assertCount(2, $requests);
        $this->assertEqualsCanonicalizing([
            $alice->id,
            $bob->id,
        ], $requests->map(fn ($request) => $request->action_payload['user_id'])->all());

        $requests->each(function (ApprovalRequest $request) {
            $this->assertCount(1, $request->recipients);
            $this->assertSame($request->action_payload['user_id'], $request->recipients->first()->user_id);
        });

        $this->assertTrue($group->users->isEmpty());
    }

    public function test_store_moves_members_from_previous_groups_after_approval(): void
    {
        $project = Project::factory()->create();

        $originalGroup = ProjectGroup::create([
            'project_id' => $project->id,
        ]);

        $alice = User::factory()->create(['name' => 'Alice']);
        $originalGroup->users()->sync([$alice->id]);

        $payload = [
            'project_id' => $project->id,
            'member_user_ids' => [$alice->id],
        ];

        $this->postJson('/api/pg/project-groups', $payload)->assertCreated();

        $originalGroup->refresh();
        $newGroup = ProjectGroup::where('project_id', $project->id)
            ->where('id', '!=', $originalGroup->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertEquals([$alice->id], $originalGroup->users->pluck('id')->all());
        $this->assertTrue($newGroup->users->isEmpty());

        $this->approveMembershipRequest($alice->id, $newGroup->id);

        $originalGroup->refresh();
        $newGroup->refresh();

        $this->assertTrue($originalGroup->users->isEmpty());
        $this->assertEquals([$alice->id], $newGroup->users->pluck('id')->all());
    }

    public function test_store_ignores_member_sync_when_null(): void
    {
        $payload = [
            'project_id' => null,
            'member_user_ids' => null,
        ];

        $response = $this->postJson('/api/pg/project-groups', $payload);

        $group = ProjectGroup::with('users')->firstOrFail();

        $response->assertCreated();
        $this->assertTrue($group->users->isEmpty());
    }

    public function test_update_syncs_members_and_handles_empty_array(): void
    {
        $project = Project::factory()->create([
            'title' => 'Proyecto de prueba',
        ]);

        $group = ProjectGroup::create([
            'project_id' => $project->id,
        ]);

        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);
        $group->users()->sync([$alice->id, $bob->id]);

        $payload = [
            'member_user_ids' => [],
        ];

        $response = $this->putJson("/api/pg/project-groups/{$group->id}", $payload);

        $group->refresh()->load('users', 'project');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' => $group->id,
                'project_id' => $project->id,
                'project_name' => $project->title,
                'member_user_ids' => [],
            ]);

        $this->assertTrue($group->users->isEmpty());
    }

    public function test_update_moves_users_from_their_previous_group(): void
    {
        $project = Project::factory()->create([
            'title' => 'Proyecto principal',
        ]);

        $groupA = ProjectGroup::create([
            'project_id' => $project->id,
        ]);
        $groupB = ProjectGroup::create([
            'project_id' => $project->id,
        ]);

        $alice = User::factory()->create(['name' => 'Alice']);
        $groupA->users()->sync([$alice->id]);

        $this->putJson("/api/pg/project-groups/{$groupB->id}", [
            'member_user_ids' => [$alice->id],
        ])->assertOk();

        $groupA->refresh();
        $groupB->refresh();

        $this->assertEquals([$alice->id], $groupA->users->pluck('id')->all());
        $this->assertTrue($groupB->users->isEmpty());

        $this->approveMembershipRequest($alice->id, $groupB->id);

        $groupA->refresh();
        $groupB->refresh();

        $this->assertTrue($groupA->users->isEmpty());
        $this->assertEquals([$alice->id], $groupB->users->pluck('id')->all());
    }

    public function test_destroy_deletes_group(): void
    {
        $group = ProjectGroup::create([
            'project_id' => Project::factory()->create(['title' => 'Temp'])->id,
        ]);

        $this->deleteJson("/api/pg/project-groups/{$group->id}")
            ->assertOk()
            ->assertExactJson(['message' => 'Project group deleted successfully']);

        $this->assertDatabaseMissing('project_groups', ['id' => $group->id]);
    }

    public function test_dropdown_returns_value_label_pairs(): void
    {
        $groupA = ProjectGroup::create([
            'project_id' => Project::factory()->create(['title' => 'Proyecto A'])->id,
        ]);
        $groupB = ProjectGroup::create([
            'project_id' => Project::factory()->create(['title' => 'Proyecto B'])->id,
        ]);

        $this->getJson('/api/pg/project-groups/dropdown')
            ->assertOk()
            ->assertExactJson([
                ['value' => $groupA->id, 'label' => 'Proyecto A'],
                ['value' => $groupB->id, 'label' => 'Proyecto B'],
            ]);
    }

        protected function approveMembershipRequest(int $userId, ?int $groupId = null): void
        {
            $query = ApprovalRequest::query()
                ->where('action_key', 'project_group.add_member')
                ->where('status', ApprovalRequest::STATUS_PENDING)
                ->where('action_payload->user_id', $userId);

            if ($groupId !== null) {
                $query->where('action_payload->group_id', $groupId);
            }

            $approvalRequest = $query->latest()->firstOrFail();

            $updated = app(ApprovalRequestService::class)->recordDecision(
                $approvalRequest,
                $userId,
                ApprovalRequest::DECISION_APPROVED
            );

            if ($updated->status !== ApprovalRequest::STATUS_PENDING) {
                app(ApprovalRequestActionRunner::class)->run(
                    $updated->fresh(),
                    $updated->resolved_decision
                );
            }
        }
}
