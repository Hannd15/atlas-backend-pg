<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Deliverable;
use App\Models\Phase;
use App\Models\Project;
use App\Models\Rubric;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectDeliverablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_expected_deliverable_states_and_grading(): void
    {
        Carbon::setTestNow('2025-04-10 12:00:00');

        [$phase] = $this->createPhaseWithPeriod();
        $project = $this->createProject($phase, 'Proyecto Estados');

        $futureDeliverable = Deliverable::create([
            'phase_id' => $phase->id,
            'name' => 'Entrega futura',
            'description' => 'Por entregar',
            'due_date' => '2025-04-15 12:00:00',
        ]);

        $pastDeliverable = Deliverable::create([
            'phase_id' => $phase->id,
            'name' => 'Entrega pasada',
            'description' => 'Sin enviar',
            'due_date' => '2025-04-05 12:00:00',
        ]);

        $submittedWithoutEvaluation = Deliverable::create([
            'phase_id' => $phase->id,
            'name' => 'Entrega enviada',
            'description' => 'Sin evaluación',
            'due_date' => '2025-04-08 12:00:00',
        ]);

        $gradedDeliverable = Deliverable::create([
            'phase_id' => $phase->id,
            'name' => 'Entrega evaluada',
            'description' => 'Evaluada',
            'due_date' => '2025-04-07 12:00:00',
        ]);

        Submission::create([
            'deliverable_id' => $submittedWithoutEvaluation->id,
            'project_id' => $project->id,
            'submission_date' => '2025-04-09 10:00:00',
        ]);

        $gradedSubmission = Submission::create([
            'deliverable_id' => $gradedDeliverable->id,
            'project_id' => $project->id,
            'submission_date' => '2025-04-06 18:00:00',
        ]);

        $student = User::factory()->create();
        $evaluator = User::factory()->create();
        $rubric = Rubric::create([
            'name' => 'General',
            'description' => 'Escala general',
            'min_value' => 0,
            'max_value' => 5,
        ]);

        $gradedSubmission->evaluations()->createMany([
            [
                'user_id' => $student->id,
                'evaluator_id' => $evaluator->id,
                'rubric_id' => $rubric->id,
                'grade' => 4.0,
                'comments' => 'Buen trabajo',
                'evaluation_date' => '2025-04-08 12:00:00',
            ],
            [
                'user_id' => $student->id,
                'evaluator_id' => $evaluator->id,
                'rubric_id' => $rubric->id,
                'grade' => 3.0,
                'comments' => 'Detalles menores',
                'evaluation_date' => '2025-04-09 12:00:00',
            ],
        ]);

        $response = $this->getJson("/api/pg/projects/{$project->id}");

        Carbon::setTestNow();

        $response->assertOk();

        $deliverables = collect($response->json('deliverables'))->keyBy('id');

        $this->assertSame('Pendiente de entrega', $deliverables[$futureDeliverable->id]['state']);
        $this->assertNull($deliverables[$futureDeliverable->id]['grading']);

        $this->assertSame('Atrasado', $deliverables[$pastDeliverable->id]['state']);
        $this->assertNull($deliverables[$pastDeliverable->id]['grading']);

        $this->assertSame('Pendiente por revisión', $deliverables[$submittedWithoutEvaluation->id]['state']);
        $this->assertNull($deliverables[$submittedWithoutEvaluation->id]['grading']);

        $this->assertSame('Al día', $deliverables[$gradedDeliverable->id]['state']);
        $this->assertSame(3.5, $deliverables[$gradedDeliverable->id]['grading']);
    }

    /**
     * @return array{0: Phase}
     */
    private function createPhaseWithPeriod(string $name = 'PG I'): array
    {
        $period = AcademicPeriod::create([
            'name' => '2025-1',
            'start_date' => '2025-01-01',
            'end_date' => '2025-06-30',
        ]);

        $phase = $period->phases()->create([
            'name' => $name,
            'start_date' => '2025-01-01',
            'end_date' => '2025-06-30',
        ]);

        return [$phase];
    }

    private function createProject(Phase $phase, string $title): Project
    {
        return Project::create([
            'title' => $title,
            'phase_id' => $phase->id,
            'status_id' => $this->ensureProjectStatus(),
        ]);
    }

    private function ensureProjectStatus(string $name = 'Activo'): int
    {
        $statusId = DB::table('project_statuses')->where('name', $name)->value('id');

        if (! $statusId) {
            $statusId = DB::table('project_statuses')->insertGetId([
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $statusId;
    }
}
