<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Evaluation;
use App\Models\Phase;
use App\Models\Project;
use App\Models\Rubric;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\PgApiResponseHelpers;
use Tests\TestCase;

class SubmissionEvaluationEndpointsTest extends TestCase
{
    use PgApiResponseHelpers;
    use RefreshDatabase;

    public function test_index_returns_submission_evaluations(): void
    {
        $submission = $this->createSubmission();
        [$studentOne, $studentTwo] = User::factory()->count(2)->create();
        [$evaluatorOne, $evaluatorTwo] = User::factory()->count(2)->create();
        $rubric = $this->createRubric('Calidad');

        $submission->evaluations()->createMany([
            [
                'user_id' => $studentOne->id,
                'evaluator_id' => $evaluatorOne->id,
                'rubric_id' => $rubric->id,
                'grade' => 4.2,
                'comments' => 'Buen trabajo',
                'evaluation_date' => '2025-04-12 15:00:00',
            ],
            [
                'user_id' => $studentTwo->id,
                'evaluator_id' => $evaluatorTwo->id,
                'rubric_id' => $rubric->id,
                'grade' => 3.9,
                'comments' => 'Puede mejorar',
                'evaluation_date' => '2025-04-13 09:30:00',
            ],
        ]);

        $response = $this->getJson("/api/pg/submissions/{$submission->id}/evaluations");

        $expected = $submission->evaluations
            ->load('user', 'evaluator', 'rubric')
            ->map(fn (Evaluation $evaluation) => $this->evaluationResource($evaluation))
            ->values()
            ->all();

        $response->assertOk()->assertExactJson($expected);
    }

    public function test_store_creates_evaluation_for_submission(): void
    {
        $submission = $this->createSubmission();
        $student = User::factory()->create();
        $evaluator = User::factory()->create();
        $rubric = $this->createRubric('Claridad');

        $payload = [
            'user_id' => $student->id,
            'evaluator_id' => $evaluator->id,
            'rubric_id' => $rubric->id,
            'grade' => 4.7,
            'comments' => 'Excelente presentación',
        ];

        $response = $this->postJson("/api/pg/submissions/{$submission->id}/evaluations", $payload);

        $evaluation = $submission->evaluations()->firstOrFail()->load('user', 'evaluator', 'rubric');

        $response->assertCreated()->assertExactJson($this->evaluationResource($evaluation));

        $this->assertDatabaseHas('evaluations', [
            'submission_id' => $submission->id,
            'user_id' => $student->id,
            'evaluator_id' => $evaluator->id,
            'rubric_id' => $rubric->id,
        ]);
    }

    public function test_show_returns_single_evaluation(): void
    {
        $submission = $this->createSubmission();
        $student = User::factory()->create();
        $evaluator = User::factory()->create();
        $rubric = $this->createRubric('Investigación');

        $evaluation = $submission->evaluations()->create([
            'user_id' => $student->id,
            'evaluator_id' => $evaluator->id,
            'rubric_id' => $rubric->id,
            'grade' => 3.5,
            'comments' => 'Debe profundizar más',
            'evaluation_date' => '2025-04-11 10:00:00',
        ]);

        $response = $this->getJson("/api/pg/submissions/{$submission->id}/evaluations/{$evaluation->id}");

        $evaluation->load('user', 'evaluator', 'rubric');

        $response->assertOk()->assertExactJson($this->evaluationResource($evaluation));
    }

    public function test_update_modifies_evaluation_fields(): void
    {
        $submission = $this->createSubmission();
        $student = User::factory()->create();
        $evaluator = User::factory()->create();
        $rubric = $this->createRubric('Impacto');

        $evaluation = $submission->evaluations()->create([
            'user_id' => $student->id,
            'evaluator_id' => $evaluator->id,
            'rubric_id' => $rubric->id,
            'grade' => 3.0,
            'comments' => 'Revisar introducción',
            'evaluation_date' => '2025-04-14 11:00:00',
        ]);

        $newEvaluator = User::factory()->create();

        $payload = [
            'grade' => 4.0,
            'comments' => 'Correcciones aplicadas',
            'evaluator_id' => $newEvaluator->id,
            'evaluation_date' => '2025-04-15 17:45:00',
        ];

        $response = $this->putJson("/api/pg/submissions/{$submission->id}/evaluations/{$evaluation->id}", $payload);

        $evaluation->refresh()->load('user', 'evaluator', 'rubric');

        $response->assertOk()->assertExactJson($this->evaluationResource($evaluation));

        $this->assertSame(4.0, (float) $evaluation->grade);
        $this->assertSame('Correcciones aplicadas', $evaluation->comments);
        $this->assertSame($newEvaluator->id, $evaluation->evaluator_id);
        $this->assertSame('2025-04-15 17:45:00', Carbon::parse($evaluation->evaluation_date)->toDateTimeString());
    }

    public function test_destroy_deletes_evaluation(): void
    {
        $submission = $this->createSubmission();
        $student = User::factory()->create();
        $evaluator = User::factory()->create();
        $rubric = $this->createRubric('Presentación');

        $evaluation = $submission->evaluations()->create([
            'user_id' => $student->id,
            'evaluator_id' => $evaluator->id,
            'rubric_id' => $rubric->id,
            'grade' => 4.1,
            'comments' => 'Excelente diseño',
            'evaluation_date' => '2025-04-16 09:00:00',
        ]);

        $this->deleteJson("/api/pg/submissions/{$submission->id}/evaluations/{$evaluation->id}")
            ->assertOk()
            ->assertExactJson(['message' => 'Evaluation deleted successfully']);

        $this->assertDatabaseMissing('evaluations', ['id' => $evaluation->id]);
    }

    public function test_show_returns_not_found_for_missing_evaluation(): void
    {
        $submission = $this->createSubmission();

        $this->getJson("/api/pg/submissions/{$submission->id}/evaluations/999999")
            ->assertNotFound();
    }

    private function createSubmission(string $projectTitle = 'Proyecto Base'): Submission
    {
        [$phase] = $this->createPhaseWithPeriod();
        $project = $this->createProject($phase, $projectTitle);

        $deliverable = $phase->deliverables()->create([
            'name' => 'Entrega Principal',
            'description' => 'Documentación clave',
            'due_date' => '2025-04-20 12:00:00',
        ]);

        return Submission::create([
            'deliverable_id' => $deliverable->id,
            'project_id' => $project->id,
            'submission_date' => '2025-04-19 10:00:00',
        ]);
    }

    private function createRubric(string $name): Rubric
    {
        return Rubric::create([
            'name' => $name,
            'description' => $name.' description',
            'min_value' => 0,
            'max_value' => 5,
        ]);
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
