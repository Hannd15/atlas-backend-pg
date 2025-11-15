<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectPosition;
use App\Models\ProjectStaff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\PgApiResponseHelpers;
use Tests\TestCase;

class ProjectPositionEndpointsTest extends TestCase
{
    use PgApiResponseHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2025-03-01 18:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_index_returns_expected_payload(): void
    {
        $position = ProjectPosition::create(['name' => 'Director']);

        $userOne = User::factory()->create(['name' => 'Alice Example', 'email' => 'alice@example.com']);
        $userTwo = User::factory()->create(['name' => 'Bob Example', 'email' => 'bob@example.com']);
        $position->eligibleUsers()->sync([$userOne->id, $userTwo->id]);

        $project = Project::factory()->create([
            'title' => 'Project Uno',
        ]);

        ProjectStaff::create([
            'project_id' => $project->id,
            'user_id' => $userOne->id,
            'project_position_id' => $position->id,
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/pg/project-positions');

        $positions = ProjectPosition::with(['eligibleUsers', 'staff'])->orderBy('updated_at', 'desc')->get();

        $response->assertOk()->assertExactJson($this->projectPositionIndexArray($positions));
    }

    public function test_store_creates_project_position(): void
    {
        $response = $this->postJson('/api/pg/project-positions', [
            'name' => 'Jurado',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('project_positions', [
            'name' => 'Jurado',
        ]);
    }

    public function test_show_returns_expected_resource(): void
    {
        $position = ProjectPosition::create(['name' => 'Director']);
        $user = User::factory()->create(['name' => 'Alice Example', 'email' => 'alice@example.com']);
        $position->eligibleUsers()->sync([$user->id]);

        $project = Project::factory()->create([
            'title' => 'Project Uno',
        ]);

        ProjectStaff::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'project_position_id' => $position->id,
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/pg/project-positions/{$position->id}");

        $position->load('eligibleUsers', 'staff');

        $response->assertOk()->assertExactJson($this->projectPositionShowResource($position));
    }

    public function test_update_updates_name_and_syncs_users(): void
    {
        $position = ProjectPosition::create(['name' => 'Director']);
        $userA = User::factory()->create(['name' => 'Alice Example', 'email' => 'alice@example.com']);
        $userB = User::factory()->create(['name' => 'Bob Example', 'email' => 'bob@example.com']);
        $position->eligibleUsers()->sync([$userA->id]);

        $payload = [
            'name' => 'Co-Director',
            'eligible_user_ids' => [$userB->id],
        ];

        $response = $this->putJson("/api/pg/project-positions/{$position->id}", $payload);

        $position->refresh();
        $position->load('eligibleUsers');
        $position->eligible_user_ids = $position->eligibleUsers->pluck('id');

        $response->assertOk()->assertExactJson($position->toArray());

        $this->assertSame('Co-Director', $position->name);
        $this->assertEquals([$userB->id], $position->eligibleUsers->pluck('id')->all());
    }

    public function test_destroy_deletes_project_position(): void
    {
        $position = ProjectPosition::create(['name' => 'Director']);

        $this->deleteJson("/api/pg/project-positions/{$position->id}")
            ->assertOk()
            ->assertExactJson(['message' => 'Project position deleted successfully']);

        $this->assertDatabaseMissing('project_positions', ['id' => $position->id]);
    }

    public function test_dropdown_returns_positions_in_default_order(): void
    {
        $first = ProjectPosition::create(['name' => 'Director']);
        $second = ProjectPosition::create(['name' => 'Jurado']);

        $this->getJson('/api/pg/project-positions/dropdown')
            ->assertOk()
            ->assertExactJson($this->projectPositionDropdownArray(ProjectPosition::all()));
    }
}
