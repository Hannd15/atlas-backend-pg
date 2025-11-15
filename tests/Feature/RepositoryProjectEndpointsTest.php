<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\GroupMember;
use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\ProjectPosition;
use App\Models\ProjectStaff;
use App\Models\Proposal;
use App\Models\RepositoryProject;
use App\Models\ThematicLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RepositoryProjectEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_expected_payload(): void
    {
        [$repositoryProject] = $this->createRepositoryProjectGraph();

        $response = $this->getJson('/api/pg/repository-projects');

        $repositoryProjects = RepositoryProject::withDetails()->orderByDesc('updated_at')->get();
        $expected = $repositoryProjects->map(fn (RepositoryProject $item) => $this->transformForIndexExpectation($item))->values()->all();

        $response->assertOk()->assertExactJson($expected);
    }

    public function test_show_returns_expected_payload(): void
    {
        [$repositoryProject] = $this->createRepositoryProjectGraph();

        $response = $this->getJson("/api/pg/repository-projects/{$repositoryProject->id}");

        $repositoryProject->loadMissing(
            'files',
            'project.groups.members.user',
            'project.staff.user',
            'project.proposal.thematicLine'
        );

        $response->assertOk()->assertExactJson($this->transformForShowExpectation($repositoryProject));
    }

    public function test_store_creates_repository_project(): void
    {
        $payload = [
            'title' => 'Repositorio de Energía',
            'description' => 'Repositorio para los proyectos de energía renovable.',
            'url' => 'https://example.com/repositorio-energia',
            'publish_date' => '2025-06-01',
            'keywords_es' => 'energía, renovable',
            'keywords_en' => 'energy, renewable',
            'abstract_es' => 'Resumen en español.',
            'abstract_en' => 'English abstract.',
        ];

        $response = $this->postJson('/api/pg/repository-projects', $payload);

        $repositoryProject = RepositoryProject::withDetails()->latest('id')->first();
        $this->assertNotNull($repositoryProject);

        $repositoryProject->loadMissing('files');

        $response->assertCreated()->assertExactJson($this->transformForShowExpectation($repositoryProject));

        $this->assertDatabaseHas('repository_projects', [
            'id' => $repositoryProject->id,
            'title' => 'Repositorio de Energía',
            'description' => 'Repositorio para los proyectos de energía renovable.',
            'url' => 'https://example.com/repositorio-energia',
            'publish_date' => '2025-06-01 00:00:00',
        ]);

        $this->assertTrue($repositoryProject->files->isEmpty());
    }

    public function test_update_modifies_repository_project_metadata(): void
    {
        [$repositoryProject] = $this->createRepositoryProjectGraph();
        $originalProjectId = $repositoryProject->project_id;
        $response = $this->postJson("/api/pg/repository-projects/{$repositoryProject->id}", [
            '_method' => 'PUT',
            'title' => 'Repositorio Actualizado',
            'description' => 'Repositorio actualizado para publicación.',
            'url' => 'https://example.com/repositorio-actualizado',
            'publish_date' => '2025-06-15',
            'keywords_es' => 'investigación, actualizado',
            'keywords_en' => 'research, updated',
            'abstract_es' => 'Resumen actualizado del proyecto.',
            'abstract_en' => 'Updated project abstract.',
        ]);

        $repositoryProject->refresh()->loadMissing('files', 'project');

        $response->assertOk()->assertExactJson($this->transformForShowExpectation($repositoryProject));

        $this->assertSame('Repositorio Actualizado', $repositoryProject->title);
        $this->assertSame('Repositorio actualizado para publicación.', $repositoryProject->description);
        $this->assertSame('https://example.com/repositorio-actualizado', $repositoryProject->url);
        $this->assertSame('2025-06-15', optional($repositoryProject->publish_date)->toDateString());

        $this->assertEquals($originalProjectId, $repositoryProject->project_id);
    }

    /**
     * @return array{0: RepositoryProject}
     */
    private function createRepositoryProjectGraph(): array
    {
        $thematicLine = ThematicLine::create([
            'name' => 'Energías Renovables',
            'description' => 'Líneas de investigación energéticas',
            'trl_expected' => 'TRL-6',
            'abet_criteria' => 'Criterio A',
            'min_score' => 75,
        ]);

        $proposer = User::factory()->create(['name' => 'Laura Proposer']);
        $author = User::factory()->create(['name' => 'Carlos Autor']);
        $advisor = User::factory()->create(['name' => 'Ana Asesora']);

        $proposal = Proposal::create([
            'title' => 'Sistema de monitoreo',
            'description' => 'Propuesta base',
            'thematic_line_id' => $thematicLine->id,
            'proposer_id' => $proposer->id,
        ]);

        $project = Project::factory()->create([
            'proposal_id' => $proposal->id,
            'title' => 'Sistema de monitoreo implementado',
        ]);

        $group = ProjectGroup::create([
            'project_id' => $project->id,
            'name' => 'Equipo Investigador',
        ]);

        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $author->id,
        ]);

        $position = ProjectPosition::create(['name' => 'Asesor']);

        ProjectStaff::create([
            'project_id' => $project->id,
            'user_id' => $advisor->id,
            'project_position_id' => $position->id,
            'status' => 'active',
        ]);

        $repositoryProject = RepositoryProject::create([
            'project_id' => $project->id,
            'title' => 'Repositorio Sistema de Monitoreo',
            'description' => 'Versión final del proyecto',
            'publish_date' => Carbon::parse('2025-05-01'),
            'keywords_es' => 'Monitorización, Energía',
            'keywords_en' => 'Monitoring, Energy',
            'abstract_es' => 'Resumen en español del proyecto.',
            'abstract_en' => 'English abstract for the project.',
        ]);

        $file = File::create([
            'name' => 'informe-final.pdf',
            'extension' => 'pdf',
            'url' => 'https://files.test/informe-final.pdf',
            'disk' => 'public',
            'path' => 'repository/informe-final.pdf',
        ]);

        $repositoryProject->files()->attach($file->id);

        return [$repositoryProject];
    }

    private function transformForIndexExpectation(RepositoryProject $repositoryProject): array
    {
        $repositoryProject->loadMissing(
            'files',
            'project.groups.members.user',
            'project.staff.user',
            'project.proposal.thematicLine'
        );

        return [
            'id' => $repositoryProject->id,
            'title' => $repositoryProject->project?->title ?? $repositoryProject->title,
            'authors' => $this->aggregateAuthorNames($repositoryProject),
            'advisors' => $this->aggregateAdvisorNames($repositoryProject),
            'keywords_es' => $repositoryProject->keywords_es,
            'thematic_line' => $repositoryProject->project?->proposal?->thematicLine?->name,
            'publish_date' => optional($repositoryProject->publish_date)->toDateString(),
            'abstract_es' => $repositoryProject->abstract_es,
            'created_at' => optional($repositoryProject->created_at)->toDateTimeString(),
            'updated_at' => optional($repositoryProject->updated_at)->toDateTimeString(),
        ];
    }

    private function transformForShowExpectation(RepositoryProject $repositoryProject): array
    {
        return array_merge(
            $this->transformForIndexExpectation($repositoryProject),
            [
                'repository_title' => $repositoryProject->title,
                'project_id' => $repositoryProject->project_id,
                'keywords_en' => $repositoryProject->keywords_en,
                'abstract_en' => $repositoryProject->abstract_en,
                'file_ids' => $repositoryProject->files->pluck('id')->values()->all(),
                'file_names' => $repositoryProject->files->pluck('name')->values()->all(),
            ]
        );
    }

    private function aggregateAuthorNames(RepositoryProject $repositoryProject): ?string
    {
        $project = $repositoryProject->project;

        if (! $project) {
            return '';
        }

        $project->loadMissing('groups.members.user');

        $values = $project->groups
            ->flatMap(fn ($group) => $group->members->map(fn ($member) => $member->user?->name))
            ->filter()
            ->unique()
            ->values();

        return $values->isEmpty() ? null : $values->implode(', ');
    }

    private function aggregateAdvisorNames(RepositoryProject $repositoryProject): ?string
    {
        $project = $repositoryProject->project;

        if (! $project) {
            return '';
        }

        $project->loadMissing('staff.user');

        $values = $project->staff
            ->filter(fn ($staff) => $staff->status === null || $staff->status === 'active')
            ->map(fn ($staff) => $staff->user?->name)
            ->filter()
            ->unique()
            ->values();

        return $values->isEmpty() ? null : $values->implode(', ');
    }
}
