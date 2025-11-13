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

        $project = Project::create([
            'proposal_id' => $proposal->id,
            'title' => 'Sistema de monitoreo implementado',
            'status' => 'completed',
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

    private function aggregateAuthorNames(RepositoryProject $repositoryProject): array
    {
        $project = $repositoryProject->project;

        if (! $project) {
            return [];
        }

        return $project->groups
            ->flatMap(fn ($group) => $group->members->map(fn ($member) => $member->user?->name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function aggregateAdvisorNames(RepositoryProject $repositoryProject): array
    {
        $project = $repositoryProject->project;

        if (! $project) {
            return [];
        }

        return $project->staff
            ->filter(fn ($staff) => $staff->status === null || $staff->status === 'active')
            ->map(fn ($staff) => $staff->user?->name)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
