<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\File;
use App\Models\Phase;
use App\Models\Project;
use App\Models\Submission;
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
            'comment' => 'Entrega inicial',
        ]);

        $file = File::create([
            'name' => 'avance.pdf',
            'extension' => 'pdf',
            'url' => 'https://files.test/avance.pdf',
            'disk' => 'public',
            'path' => 'pg/uploads/avance.pdf',
        ]);
        $submission->files()->attach($file);

        // Evaluations creation removed from submission payload; handled via dedicated endpoints.

        $phase->load('period');
        $period = $phase->period;

        $response = $this->getJson($this->submissionPath($period->id, $phase->id, $deliverable->id, $project->id));

        $expected = Submission::where('deliverable_id', $deliverable->id)
            ->where('project_id', $project->id)
            ->with('deliverable.phase.period', 'project', 'files', 'evaluations')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Submission $item) => $this->submissionResource($item))
            ->values()
            ->all();

        $response->assertOk()->assertExactJson($expected);
    }

    public function test_store_creates_submission_with_comment(): void
    {
        [$phase] = $this->createPhaseWithPeriod();
        $project = $this->createProject($phase, 'Proyecto Final');
        $deliverable = $phase->deliverables()->create([
            'name' => 'Entrega Final',
            'description' => 'Documento final del proyecto',
            'due_date' => '2025-05-01 12:00:00',
        ]);

        $phase->load('period');
        $period = $phase->period;

        $payload = [
            'comment' => 'Entrega final subida',
        ];

        $response = $this->postJson($this->submissionPath($period->id, $phase->id, $deliverable->id, $project->id), $payload);

        $createdId = $response->json('id');
        $this->assertNotNull($createdId, 'Submission id missing in response');

        $submission = Submission::with('deliverable.phase.period', 'project', 'files', 'evaluations')->findOrFail($createdId);

        $response->assertCreated()->assertExactJson($this->submissionResource($submission));

        $this->assertSame('Entrega final subida', $submission->comment);
        $this->assertSame(Carbon::now()->toDateTimeString(), $submission->submission_date?->toDateTimeString());

        $this->assertDatabaseHas('submissions', [
            'id' => $submission->id,
            'deliverable_id' => $deliverable->id,
            'project_id' => $project->id,
            'comment' => 'Entrega final subida',
        ]);

        $this->assertTrue($submission->files->isEmpty());

        // No evaluation assertions; evaluations created via separate endpoints now.
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

        $phase->load('period');
        $period = $phase->period;

        $response = $this->getJson($this->submissionPath($period->id, $phase->id, $deliverable->id, $project->id, $submission->id));

        $submission->load('deliverable.phase.period', 'project.status', 'files', 'evaluations.user', 'evaluations.evaluator', 'evaluations.rubric');

        $response->assertOk()->assertExactJson($this->submissionResource($submission));
    }

    public function test_update_overwrites_comment(): void
    {
        [$phase] = $this->createPhaseWithPeriod();
        $project = $this->createProject($phase, 'Proyecto Beta');
        $deliverable = $phase->deliverables()->create([
            'name' => 'Entrega 2',
            'description' => 'Entrega intermedia',
            'due_date' => '2025-04-25 17:00:00',
        ]);

        $submission = Submission::create([
            'deliverable_id' => $deliverable->id,
            'project_id' => $project->id,
            'submission_date' => '2025-04-20 09:00:00',
            'comment' => 'Comentario inicial',
        ]);

        $phase->load('period');
        $period = $phase->period;

        $payload = [
            'comment' => 'Comentario actualizado',
        ];

        $response = $this->putJson($this->submissionPath($period->id, $phase->id, $deliverable->id, $project->id, $submission->id), $payload);

        $submission->refresh()->load('deliverable.phase.period', 'project', 'files', 'evaluations');

        $response->assertOk()->assertExactJson($this->submissionResource($submission));

        $this->assertSame('Comentario actualizado', $submission->comment);
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

        $phase->load('period');
        $period = $phase->period;

        $this->deleteJson($this->submissionPath($period->id, $phase->id, $deliverable->id, $project->id, $submission->id))
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

    private function submissionPath(int $periodId, int $phaseId, int $deliverableId, int $projectId, ?int $submissionId = null): string
    {
        $base = "/api/pg/academic-periods/{$periodId}/phases/{$phaseId}/deliverables/{$deliverableId}/projects/{$projectId}/submissions";

        return $submissionId === null ? $base : $base.'/'.$submissionId;
    }
}
