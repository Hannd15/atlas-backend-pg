<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\File;
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

class SubmissionEndpointsTest extends TestCase
{
    use PgApiResponseHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2025-04-01 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_index_returns_expected_payload(): void
    {
        [$phase] = $this->createPhaseWithPeriod();
        $project = $this->createProject($phase, 'Proyecto Integrador');
        $deliverable = $phase->deliverables()->create([
            'name' => 'Entrega Parcial',
            'description' => 'Documento base del proyecto',
            'due_date' => '2025-04-15 18:00:00',
        ]);

        $submission = Submission::create([
            'deliverable_id' => $deliverable->id,
            'project_id' => $project->id,
            'submission_date' => '2025-04-10 09:00:00',
        ]);

        $file = File::create([
            'name' => 'avance.pdf',
            'extension' => 'pdf',
            'url' => 'https://files.test/avance.pdf',
            'disk' => 'public',
            'path' => 'pg/uploads/avance.pdf',
        ]);
        $submission->files()->attach($file);

        $rubric = Rubric::create([
            'name' => 'Calidad técnica',
            'description' => 'Se evalúa la calidad técnica del proyecto',
            'min_value' => 0,
            'max_value' => 5,
        ]);

        $student = User::factory()->create();
        $evaluator = User::factory()->create();

        $submission->evaluations()->create([
            'user_id' => $student->id,
            'evaluator_id' => $evaluator->id,
            'rubric_id' => $rubric->id,
            'grade' => 4.8,
            'comments' => 'Excelente avance',
            'evaluation_date' => '2025-04-11 12:00:00',
        ]);

        $response = $this->getJson('/api/pg/submissions');

        $expected = Submission::with('deliverable.phase.period', 'project.status', 'files', 'evaluations.user', 'evaluations.evaluator', 'evaluations.rubric')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Submission $item) => $this->submissionResource($item))
            ->values()
            ->all();

        $response->assertOk()->assertExactJson($expected);
    }

    public function test_store_creates_submission_with_files_and_evaluations(): void
    {
        [$phase] = $this->createPhaseWithPeriod();
        $project = $this->createProject($phase, 'Proyecto Final');
        $deliverable = $phase->deliverables()->create([
            'name' => 'Entrega Final',
            'description' => 'Documento final del proyecto',
            'due_date' => '2025-05-01 12:00:00',
        ]);

        $file = File::create([
            'name' => 'final.pdf',
            'extension' => 'pdf',
            'url' => 'https://files.test/final.pdf',
            'disk' => 'public',
            'path' => 'pg/uploads/final.pdf',
        ]);

        $rubric = Rubric::create([
            'name' => 'Impacto',
            'description' => 'Impacto del proyecto',
            'min_value' => 0,
            'max_value' => 5,
        ]);

        $student = User::factory()->create();
        $evaluator = User::factory()->create();

        $payload = [
            'deliverable_id' => $deliverable->id,
            'project_id' => $project->id,
            'submission_date' => '2025-04-28 16:00:00',
            'file_ids' => [$file->id],
            'evaluations' => [
                [
                    'user_id' => $student->id,
                    'evaluator_id' => $evaluator->id,
                    'rubric_id' => $rubric->id,
                    'grade' => 4.5,
                    'comments' => 'Muy bien logrado',
                ],
            ],
        ];

        $response = $this->postJson('/api/pg/submissions', $payload);

        $submission = Submission::with('deliverable.phase.period', 'project.status', 'files', 'evaluations.user', 'evaluations.evaluator', 'evaluations.rubric')->firstOrFail();

        $response->assertCreated()->assertExactJson($this->submissionResource($submission));

        $this->assertDatabaseHas('submissions', [
            'deliverable_id' => $deliverable->id,
            'project_id' => $project->id,
            'submission_date' => '2025-04-28 16:00:00',
        ]);

        $this->assertDatabaseHas('submission_files', [
            'submission_id' => $submission->id,
            'file_id' => $file->id,
        ]);

        $this->assertDatabaseHas('evaluations', [
            'submission_id' => $submission->id,
            'user_id' => $student->id,
            'evaluator_id' => $evaluator->id,
            'rubric_id' => $rubric->id,
        ]);
    }

    public function test_show_returns_expected_resource(): void
    {
        [$phase] = $this->createPhaseWithPeriod();
        $project = $this->createProject($phase, 'Proyecto Alpha');
        $deliverable = $phase->deliverables()->create([
            'name' => 'Revisión 1',
            'description' => 'Primer entrega',
            'due_date' => '2025-04-20 12:00:00',
        ]);

        $submission = Submission::create([
            'deliverable_id' => $deliverable->id,
            'project_id' => $project->id,
            'submission_date' => '2025-04-18 10:00:00',
        ]);

        $response = $this->getJson("/api/pg/submissions/{$submission->id}");

        $submission->load('deliverable.phase.period', 'project.status', 'files', 'evaluations.user', 'evaluations.evaluator', 'evaluations.rubric');

        $response->assertOk()->assertExactJson($this->submissionResource($submission));
    }

    public function test_update_syncs_files_and_submission_fields(): void
    {
        [$phase] = $this->createPhaseWithPeriod();
        $otherPhase = $this->createPhaseWithPeriod('PG II')[0];
        $project = $this->createProject($phase, 'Proyecto Beta');
        $newProject = $this->createProject($otherPhase, 'Proyecto Gamma');
        $deliverable = $phase->deliverables()->create([
            'name' => 'Entrega 2',
            'description' => 'Entrega intermedia',
            'due_date' => '2025-04-25 17:00:00',
        ]);

        $submission = Submission::create([
            'deliverable_id' => $deliverable->id,
            'project_id' => $project->id,
            'submission_date' => '2025-04-20 09:00:00',
        ]);

        $fileA = File::create([
            'name' => 'borrador.docx',
            'extension' => 'docx',
            'url' => 'https://files.test/borrador.docx',
            'disk' => 'public',
            'path' => 'pg/uploads/borrador.docx',
        ]);
        $fileB = File::create([
            'name' => 'presentacion.pptx',
            'extension' => 'pptx',
            'url' => 'https://files.test/presentacion.pptx',
            'disk' => 'public',
            'path' => 'pg/uploads/presentacion.pptx',
        ]);

        $payload = [
            'deliverable_id' => $deliverable->id,
            'project_id' => $newProject->id,
            'submission_date' => '2025-04-22 14:30:00',
            'file_ids' => [$fileA->id, $fileB->id],
        ];

        $response = $this->putJson("/api/pg/submissions/{$submission->id}", $payload);

        $submission->refresh()->load('deliverable.phase.period', 'project.status', 'files', 'evaluations');

        $response->assertOk()->assertExactJson($this->submissionResource($submission));

        $this->assertSame('2025-04-22 14:30:00', $submission->submission_date?->toDateTimeString());
        $this->assertSame($newProject->id, $submission->project_id);
        $this->assertEqualsCanonicalizing([$fileA->id, $fileB->id], $submission->files->pluck('id')->all());
    }

    public function test_destroy_removes_submission(): void
    {
        [$phase] = $this->createPhaseWithPeriod();
        $project = $this->createProject($phase, 'Proyecto Delta');
        $deliverable = $phase->deliverables()->create([
            'name' => 'Entrega 3',
            'description' => null,
            'due_date' => '2025-04-30 12:00:00',
        ]);

        $submission = Submission::create([
            'deliverable_id' => $deliverable->id,
            'project_id' => $project->id,
            'submission_date' => '2025-04-29 16:00:00',
        ]);

        $this->deleteJson("/api/pg/submissions/{$submission->id}")
            ->assertOk()
            ->assertExactJson(['message' => 'Submission deleted successfully']);

        $this->assertDatabaseMissing('submissions', ['id' => $submission->id]);
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
