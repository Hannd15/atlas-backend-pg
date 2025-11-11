<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_expected_payload(): void
    {
        $project = Project::create([
            'title' => 'Sistema de seguimiento',
            'status' => 'active',
        ]);

        ProjectGroup::create([
            'project_id' => $project->id,
            'name' => 'Grupo A',
        ]);
        ProjectGroup::create([
            'project_id' => $project->id,
            'name' => 'Grupo B',
        ]);

        $response = $this->getJson('/api/pg/projects');

        $projects = Project::with('proposal', 'groups.members.user')->orderByDesc('updated_at')->get();

        $expected = $projects->map(fn (Project $item) => $this->transformForExpectation($item))->values()->all();

        $response->assertOk()->assertExactJson($expected);
    }

    public function test_store_creates_project(): void
    {
        $payload = [
            'title' => 'Plataforma educativa',
            'status' => 'draft',
            'proposal_id' => null,
        ];

        $response = $this->postJson('/api/pg/projects', $payload);

        $project = Project::with('proposal', 'groups.members.user')->firstOrFail();

        $response->assertCreated()->assertExactJson($this->transformForExpectation($project));

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'title' => 'Plataforma educativa',
            'status' => 'draft',
        ]);
    }

    public function test_show_returns_expected_payload(): void
    {
        $project = Project::create([
            'title' => 'Aplicativo móvil',
            'status' => 'active',
        ]);

        ProjectGroup::create([
            'project_id' => $project->id,
            'name' => 'Equipo 1',
        ]);

        $response = $this->getJson("/api/pg/projects/{$project->id}");

        $project->load('proposal', 'groups.members.user');

        $response->assertOk()->assertExactJson($this->transformForExpectation($project));
    }

    public function test_update_modifies_project_fields(): void
    {
        $project = Project::create([
            'title' => 'Servicio web',
            'status' => 'active',
        ]);

        $payload = [
            'title' => 'Servicio web actualizado',
            'status' => 'archived',
        ];

        $response = $this->putJson("/api/pg/projects/{$project->id}", $payload);

        $project->refresh()->load('proposal', 'groups.members.user');

        $response->assertOk()->assertExactJson($this->transformForExpectation($project));

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'title' => 'Servicio web actualizado',
            'status' => 'archived',
        ]);
    }

    public function test_destroy_deletes_project(): void
    {
        $project = Project::create([
            'title' => 'Sistema temporal',
            'status' => 'active',
        ]);

        $this->deleteJson("/api/pg/projects/{$project->id}")
            ->assertOk()
            ->assertExactJson(['message' => 'Project deleted successfully']);

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_dropdown_returns_value_label_pairs(): void
    {
        $projectA = Project::create(['title' => 'Proyecto A', 'status' => 'active']);
        $projectB = Project::create(['title' => 'Proyecto B', 'status' => 'active']);

        $this->getJson('/api/pg/projects/dropdown')
            ->assertOk()
            ->assertExactJson([
                ['value' => $projectA->id, 'label' => 'Proyecto A'],
                ['value' => $projectB->id, 'label' => 'Proyecto B'],
            ]);
    }

    private function transformForExpectation(Project $project): array
    {
        return [
            'id' => $project->id,
            'title' => $project->title,
            'status' => $project->status,
            'proposal_id' => $project->proposal_id,
            'group_ids' => $project->groups->pluck('id')->values()->all(),
            'group_names' => $project->groups->pluck('name')->implode(', '),
            'created_at' => optional($project->created_at)->toDateTimeString(),
            'updated_at' => optional($project->updated_at)->toDateTimeString(),
        ];
    }
}
