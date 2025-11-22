<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticatedUserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_user_profile_with_projects_and_group(): void
    {
        $user = User::factory()->create([
            'id' => 777,
            'name' => 'Alice Example',
            'email' => 'alice@example.com',
        ]);

        $project = Project::factory()->create();
        $group = ProjectGroup::create(['project_id' => $project->id]);
        $group->members()->create(['user_id' => $user->id]);

        $this->atlasAuthServiceFake->setUserPayload([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => 'https://example.com/avatar.png',
            'roles_list' => [1, 2],
            'roles_names' => ['Director', 'Jurado'],
            'permissions_list' => [10],
            'permissions_names' => ['view-projects'],
        ]);

        $this->getJson('/api/pg/auth/user-profile')
            ->assertOk()
            ->assertExactJson([
                'id' => $user->id,
                'name' => 'Alice Example',
                'email' => 'alice@example.com',
                'avatar' => 'https://example.com/avatar.png',
                'roles' => [],
                'roles_list' => [1, 2],
                'roles_names' => ['Director', 'Jurado'],
                'permissions_list' => [10],
                'permissions_names' => ['view-projects'],
                'projects' => [$project->id],
                'group_id' => $group->id,
            ]);
    }

    public function test_returns_group_id_even_when_group_has_no_project(): void
    {
        $user = User::factory()->create([
            'id' => 1200,
            'name' => 'Bob Without Project',
            'email' => 'bob@example.com',
        ]);

        $group = ProjectGroup::create(['project_id' => null]);
        $group->members()->create(['user_id' => $user->id]);

        $this->atlasAuthServiceFake->setUserPayload([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => ['student'],
        ]);

        $this->getJson('/api/pg/auth/user-profile')
            ->assertOk()
            ->assertExactJson([
                'id' => $user->id,
                'name' => 'Bob Without Project',
                'email' => 'bob@example.com',
                'roles' => ['student'],
                'projects' => [],
                'group_id' => $group->id,
            ]);
    }
}
