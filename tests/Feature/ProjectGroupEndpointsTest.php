<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectGroupEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_expected_payload(): void
    {
        $project = Project::factory()->create([
            'title' => 'Sistema de gestión',
        ]);

        $group = ProjectGroup::create([
            'project_id' => $project->id,
            'name' => 'Grupo Alfa',
        ]);

        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);
        $group->users()->sync([$alice->id, $bob->id]);

        $response = $this->getJson('/api/pg/project-groups');

        $groups = ProjectGroup::with('project.phase.period', 'users')->orderByDesc('updated_at')->get();

        $expected = $groups->map(fn (ProjectGroup $item) => [
            'id' => $item->id,
            'name' => $item->name,
            'project_id' => $item->project_id,
            'project_name' => $item->project?->title,
            'phase_name' => $item->project?->phase?->name,
            'period_name' => $item->project?->phase?->period?->name,
            'member_user_ids' => $item->users->pluck('id')->values()->all(),
            'member_user_names' => $item->users->pluck('name')->implode(', '),
            'created_at' => optional($item->created_at)->toDateTimeString(),
            'updated_at' => optional($item->updated_at)->toDateTimeString(),
        ])->values()->all();

        $response->assertOk()->assertExactJson($expected);
    }

    public function test_store_creates_group_and_syncs_members(): void
    {
        $project = Project::factory()->create([
            'title' => 'Proyecto académico',
        ]);

        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);

        $payload = [
            'name' => 'Equipo A',
            'project_id' => $project->id,
            'member_user_ids' => [$alice->id, $bob->id],
        ];

        $response = $this->postJson('/api/pg/project-groups', $payload);

        $group = ProjectGroup::with('project', 'users')->firstOrFail();

        $response->assertCreated()->assertExactJson([
            'id' => $group->id,
            'name' => 'Equipo A',
            'project_id' => $project->id,
            'project_name' => $project->title,
            'member_user_ids' => $group->users->pluck('id')->values()->all(),
            'member_user_names' => $group->users->pluck('name')->implode(', '),
            'created_at' => optional($group->created_at)->toDateTimeString(),
            'updated_at' => optional($group->updated_at)->toDateTimeString(),
        ]);

        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $alice->id,
        ]);
        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $bob->id,
        ]);
    }

    public function test_store_ignores_member_sync_when_null(): void
    {
        $payload = [
            'name' => 'Equipo sin asignación',
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
            'name' => 'Equipo original',
        ]);

        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);
        $group->users()->sync([$alice->id, $bob->id]);

        $payload = [
            'name' => 'Equipo actualizado',
            'member_user_ids' => [],
        ];

        $response = $this->putJson("/api/pg/project-groups/{$group->id}", $payload);

        $group->refresh()->load('users', 'project');

        $response->assertOk()->assertExactJson([
            'id' => $group->id,
            'name' => 'Equipo actualizado',
            'project_id' => $project->id,
            'project_name' => $project->title,
            'member_user_ids' => [],
            'member_user_names' => '',
            'created_at' => optional($group->created_at)->toDateTimeString(),
            'updated_at' => optional($group->updated_at)->toDateTimeString(),
        ]);

        $this->assertTrue($group->users->isEmpty());
    }

    public function test_update_rejects_users_already_in_another_group(): void
    {
        $project = Project::factory()->create([
            'title' => 'Proyecto principal',
        ]);

        $groupA = ProjectGroup::create([
            'project_id' => $project->id,
            'name' => 'Grupo A',
        ]);
        $groupB = ProjectGroup::create([
            'project_id' => $project->id,
            'name' => 'Grupo B',
        ]);

        $alice = User::factory()->create(['name' => 'Alice']);
        $groupA->users()->sync([$alice->id]);

        $response = $this->putJson("/api/pg/project-groups/{$groupB->id}", [
            'member_user_ids' => [$alice->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['member_user_ids']);
    }

    public function test_destroy_deletes_group(): void
    {
        $group = ProjectGroup::create([
            'project_id' => Project::factory()->create(['title' => 'Temp'])->id,
            'name' => 'Temporal',
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
            'name' => 'Equipo A',
        ]);
        $groupB = ProjectGroup::create([
            'project_id' => Project::factory()->create(['title' => 'Proyecto B'])->id,
            'name' => 'Equipo B',
        ]);

        $this->getJson('/api/pg/project-groups/dropdown')
            ->assertOk()
            ->assertExactJson([
                ['value' => $groupA->id, 'label' => 'Equipo A'],
                ['value' => $groupB->id, 'label' => 'Equipo B'],
            ]);
    }
}
