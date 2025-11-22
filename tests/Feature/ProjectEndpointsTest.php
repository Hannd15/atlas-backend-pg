<?php

namespace Tests\Feature;

use App\Models\Deliverable;
use App\Models\GroupMember;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\ProjectPosition;
use App\Models\ProjectStaff;
use App\Models\ProjectStatus;
use App\Models\RepositoryProject;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = false;

    public function test_index_returns_expected_payload(): void
    {
        $project = Project::factory()->create([
            'title' => 'Sistema de seguimiento',
        ]);

        $groupA = ProjectGroup::create([
            'project_id' => $project->id,
        ]);
        $groupB = ProjectGroup::create([
            'project_id' => $project->id,
        ]);

        $userOne = User::factory()->create(['name' => 'Ada Lovelace']);
        $userTwo = User::factory()->create(['name' => 'Grace Hopper']);

        GroupMember::create([
            'group_id' => $groupA->id,
            'user_id' => $userOne->id,
        ]);

        GroupMember::create([
            'group_id' => $groupB->id,
            'user_id' => $userTwo->id,
        ]);

        $position = ProjectPosition::create(['name' => 'Director']);
        $staffUser = User::factory()->create(['name' => 'Director Uno']);

        ProjectStaff::create([
            'project_id' => $project->id,
            'user_id' => $staffUser->id,
            'project_position_id' => $position->id,
            'status' => 'active',
        ]);

        $project->refresh();
        $phase = $project->phase()->firstOrFail();

        $deliverable = Deliverable::create([
            'phase_id' => $phase->id,
            'name' => 'Plan de trabajo',
            'description' => 'Documento inicial',
            'due_date' => now()->addWeek(),
        ]);

        Submission::create([
            'deliverable_id' => $deliverable->id,
            'project_id' => $project->id,
            'submission_date' => now(),
        ]);

        $creator = User::factory()->create(['name' => 'Facilitador']);

        $meeting = Meeting::create([
            'project_id' => $project->id,
            'meeting_date' => now()->toDateString(),
            'observations' => 'Kickoff inicial',
            'created_by' => $creator->id,
        ]);

        $meeting->attendees()->sync([$creator->id, $staffUser->id, $userOne->id]);

        $response = $this->getJson('/api/pg/projects');

        $projects = Project::orderByDesc('updated_at')->with(['groups.members.user', 'status', 'thematicLine'])->get();

        $expected = $projects->map(fn (Project $item) => $this->transformForIndexExpectation($item))->values()->all();

        $response->assertOk()->assertExactJson($expected);
    }

    public function test_store_creates_project(): void
    {
        $activoStatusId = \App\Models\ProjectStatus::firstOrCreate(['name' => 'Activo'])->id;

        $payload = [
            'title' => 'Plataforma educativa',
            'proposal_id' => null,
        ];

        $response = $this->postJson('/api/pg/projects', $payload);

        $project = Project::firstOrFail();

        $response->assertCreated()->assertExactJson($this->transformForExpectation($project));

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'title' => 'Plataforma educativa',
            'status_id' => $activoStatusId,
        ]);
    }

    public function test_store_auto_sets_status_to_activo_when_not_provided(): void
    {
        $payload = [
            'title' => 'Proyecto sin estado',
            'proposal_id' => null,
        ];

        $response = $this->postJson('/api/pg/projects', $payload);

        $project = Project::firstOrFail();
        $activoStatus = \App\Models\ProjectStatus::where('name', 'Activo')->firstOrFail();

        $response->assertCreated();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'title' => 'Proyecto sin estado',
            'status_id' => $activoStatus->id,
        ]);

        $this->assertSame('Activo', $project->status->name);
    }

    public function test_show_returns_expected_payload(): void
    {
        $project = Project::factory()->create([
            'title' => 'Aplicativo móvil',
        ]);

        $group = ProjectGroup::create([
            'project_id' => $project->id,
        ]);

        $user = User::factory()->create(['name' => 'Linus Torvalds']);

        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
        ]);

        $response = $this->getJson("/api/pg/projects/{$project->id}");

        $project->refresh();

        $response->assertOk()->assertExactJson($this->transformForExpectation($project));
    }

    public function test_update_modifies_project_fields(): void
    {
        $project = Project::factory()->create([
            'title' => 'Servicio web',
        ]);

        $statusId = \App\Models\ProjectStatus::where('name', 'Terminado')->first()->id;

        $payload = [
            'title' => 'Servicio web actualizado',
            'status_id' => $statusId,
        ];

        $response = $this->putJson("/api/pg/projects/{$project->id}", $payload);

        $project->refresh();

        $response->assertOk()->assertExactJson($this->transformForExpectation($project));

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'title' => 'Servicio web actualizado',
            'status_id' => $statusId,
        ]);
    }

    public function test_update_ignores_proposal_id_changes(): void
    {
        $project = Project::factory()->create([
            'title' => 'Proyecto restringido',
        ]);

        $response = $this->putJson("/api/pg/projects/{$project->id}", [
            'title' => 'Proyecto restringido actualizado',
            'proposal_id' => 999,
        ]);

        $project->refresh();

        $response->assertOk();

        $this->assertSame('Proyecto restringido actualizado', $project->title);
        $this->assertNull($project->proposal_id);
    }

    public function test_destroy_deletes_project(): void
    {
        $project = Project::factory()->create([
            'title' => 'Sistema temporal',
        ]);

        $this->deleteJson("/api/pg/projects/{$project->id}")
            ->assertOk()
            ->assertExactJson(['message' => 'Project deleted successfully']);

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_dropdown_returns_value_label_pairs(): void
    {
        $projectA = Project::factory()->create(['title' => 'Proyecto A']);
        $projectB = Project::factory()->create(['title' => 'Proyecto B']);

        $this->getJson('/api/pg/projects/dropdown')
            ->assertOk()
            ->assertExactJson([
                ['value' => $projectA->id, 'label' => 'Proyecto A'],
                ['value' => $projectB->id, 'label' => 'Proyecto B'],
            ]);
    }

    public function test_completed_dropdown_returns_only_finished_projects_without_repository(): void
    {
        $terminadoStatus = ProjectStatus::firstOrCreate(['name' => 'Terminado']);
        $activoStatus = ProjectStatus::firstOrCreate(['name' => 'Activo']);

        $eligibleProject = Project::factory()->create([
            'title' => 'Proyecto Terminado Elegible',
            'status_id' => $terminadoStatus->id,
        ]);

        $assignedProject = Project::factory()->create([
            'title' => 'Proyecto Terminado Asignado',
            'status_id' => $terminadoStatus->id,
        ]);

        RepositoryProject::create([
            'project_id' => $assignedProject->id,
            'title' => 'Repositorio de proyecto asignado',
        ]);

        Project::factory()->create([
            'title' => 'Proyecto Activo',
            'status_id' => $activoStatus->id,
        ]);

        $this->getJson('/api/pg/projects/dropdown/completed')
            ->assertOk()
            ->assertExactJson([
                ['value' => $eligibleProject->id, 'label' => 'Proyecto Terminado Elegible'],
            ]);
    }

    private function transformForIndexExpectation(Project $project): array
    {
        $project->loadMissing('groups.members.user', 'status', 'thematicLine');

        return [
            'id' => $project->id,
            'title' => $project->title,
            'status' => $project->status?->name,
            'description' => $project->description,
            'thematic_line_name' => $project->thematicLine?->name,
            'member_names' => $this->memberNames($project),
            'created_at' => optional($project->created_at)->toDateTimeString(),
            'updated_at' => optional($project->updated_at)->toDateTimeString(),
        ];
    }

    private function transformForExpectation(Project $project): array
    {
        $project->loadMissing([
            'thematicLine',
            'groups.members.user',
            'status',
            'staff.position',
        ]);

        return [
            'id' => $project->id,
            'title' => $project->title,
            'status' => $project->status?->name,
            'proposal_id' => $project->proposal_id,
            'description' => $project->description,
            'thematic_line_id' => $project->thematic_line_id,
            'thematic_line_name' => $project->thematicLine?->name,
            'member_names' => $this->memberNames($project),
            'staff_names' => $project->staff
                ->map(fn (ProjectStaff $staff) => $staff->position?->name)
                ->filter()
                ->unique()
                ->implode(', '),
            'created_at' => optional($project->created_at)->toDateTimeString(),
            'updated_at' => optional($project->updated_at)->toDateTimeString(),
        ];
    }

    private function memberNames(Project $project): string
    {
        $project->loadMissing('groups.members.user');

        return $project->groups
            ->flatMap(fn ($group) => $group->members->map(fn ($member) => $member->user?->name))
            ->filter()
            ->unique()
            ->values()
            ->implode(', ');
    }
}
