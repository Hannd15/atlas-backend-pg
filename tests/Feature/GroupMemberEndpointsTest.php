<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\GroupMember;
use App\Models\Phase;
use App\Models\Project;
use App\Models\ProjectGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GroupMemberEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = false;

    public function test_index_returns_group_members_with_names(): void
    {
        // Create phase structure
        $period = AcademicPeriod::factory()->create();
        $phase = Phase::factory()->create(['period_id' => $period->id]);

        // Create users
        $user1 = \App\Models\User::factory()->create();
        $user2 = \App\Models\User::factory()->create();

        // Create project and group
        $project = Project::factory()->create(['phase_id' => $phase->id]);
        $group = ProjectGroup::create([
            'project_id' => $project->id,
            'name' => 'Team Alpha',
        ]);

        // Add members
        GroupMember::create(['group_id' => $group->id, 'user_id' => $user1->id]);
        GroupMember::create(['group_id' => $group->id, 'user_id' => $user2->id]);

        // Mock Atlas service
        Http::fake([
            'https://auth.example/api/users/names' => Http::response([
                $user1->id => 'Alice Smith',
                $user2->id => 'Bob Johnson',
            ]),
        ]);

        config(['services.atlas_auth.url' => 'https://auth.example']);

        $response = $this->withToken('test-token')
            ->getJson("/api/pg/project-groups/{$group->id}/members");

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJsonStructure([
                '*' => ['user_id', 'user_name'],
            ])
            ->assertJson([
                ['user_id' => $user1->id],
                ['user_id' => $user2->id],
            ]);
    }

    public function test_index_returns_empty_array_when_no_members(): void
    {
        // Create phase structure
        $period = AcademicPeriod::factory()->create();
        $phase = Phase::factory()->create(['period_id' => $period->id]);

        // Create project and group with no members
        $project = Project::factory()->create(['phase_id' => $phase->id]);
        $group = ProjectGroup::create([
            'project_id' => $project->id,
            'name' => 'Empty Team',
        ]);

        $response = $this->withToken('test-token')
            ->getJson("/api/pg/project-groups/{$group->id}/members");

        $response->assertOk()
            ->assertExactJson([]);
    }

    public function test_index_returns_404_for_nonexistent_group(): void
    {
        $response = $this->withToken('test-token')
            ->getJson('/api/pg/project-groups/999/members');

        $response->assertNotFound();
    }
}
