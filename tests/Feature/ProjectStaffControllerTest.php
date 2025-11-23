<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectPosition;
use App\Models\ProjectStaff;
use App\Models\User;
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

    public function test_store_assigns_user_to_position(): void
    {
        $project = Project::factory()->create();
        $position = ProjectPosition::create(['name' => 'Director']);
        $user = User::factory()->create(['name' => 'Bob Example']);

        $this->postJson(
            "/api/pg/projects/{$project->id}/project-positions/{$position->id}/users/{$user->id}/staff",
            [],
            $this->defaultHeaders
        )
            ->assertCreated()
            ->assertExactJson([
                'position' => 'Director',
                'user_name' => 'Bob Example',
            ]);

        $this->assertDatabaseHas('project_staff', [
            'project_id' => $project->id,
            'project_position_id' => $position->id,
            'user_id' => $user->id,
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
