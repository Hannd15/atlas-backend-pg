<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\File;
use App\Models\Phase;
use App\Models\Project;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubmissionFileEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = false;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2025-04-01 12:00:00');
        Storage::fake('public');
        config(['filesystems.default' => 'public']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_get_all_returns_submission_file_metadata(): void
    {
        [$submission, $file] = $this->createSubmissionAndFile();
        $submission->files()->attach($file);

        $response = $this->getJson('/api/pg/submission-files');

        $response->assertOk()->assertExactJson([
            [
                'file_id' => $file->id,
                'submission_id' => $submission->id,
                'name' => $file->name,
                'extension' => $file->extension,
                'url' => $file->url,
            ],
        ]);
    }

    public function test_index_returns_files_for_submission(): void
    {
        [$submission, $file] = $this->createSubmissionAndFile();
        $submission->files()->attach($file);

        // Refresh to load relationships
        $submission = $submission->fresh(['deliverable.phase.period', 'project']);

        $response = $this->getJson($this->submissionFilesPath($submission));

        $response->assertOk()
            ->assertExactJson([
                [
                    'id' => $file->id,
                    'name' => $file->name,
                    'extension' => $file->extension,
                ],
            ]);
    }

    public function test_store_uploads_file_and_associates(): void
    {
        [$submission] = $this->createSubmissionAndFile(includeFile: false);

        // Refresh to load relationships
        $submission = $submission->fresh(['deliverable.phase.period', 'project']);

        $uploaded = UploadedFile::fake()->create('avance.pdf', 120, 'application/pdf');

        $response = $this->postJson($this->submissionFilesPath($submission), [
            'file' => $uploaded,
        ]);

        $response->assertCreated()
            ->assertJsonPath('submission_id', $submission->id)
            ->assertJsonPath('name', 'avance.pdf')
            ->assertJsonPath('extension', 'pdf');

        $fileId = $response->json('id');
        $file = File::findOrFail($fileId);

        $this->assertTrue($submission->files()->where('file_id', $fileId)->exists());
        $this->assertTrue(Storage::disk($file->disk)->exists($file->path));
    }

    /**
     * @return array{0: Submission, 1?: File}
     */
    private function createSubmissionAndFile(bool $includeFile = true): array
    {
        [$phase] = $this->createPhaseWithPeriod();
        $project = $this->createProject($phase, 'Proyecto Integrador');

        $deliverable = $phase->deliverables()->create([
            'name' => 'Entrega Parcial',
            'description' => 'Documento inicial',
            'due_date' => '2025-04-20 12:00:00',
        ]);

        $submission = Submission::create([
            'deliverable_id' => $deliverable->id,
            'project_id' => $project->id,
            'submission_date' => '2025-04-18 10:00:00',
        ]);

        if (! $includeFile) {
            return [$submission];
        }

        $file = File::create([
            'name' => 'avance.pdf',
            'extension' => 'pdf',
            'url' => 'https://files.test/avance.pdf',
            'disk' => 'public',
            'path' => 'pg/uploads/avance.pdf',
        ]);

        Storage::disk('public')->put($file->path, 'contenido');

        return [$submission, $file];
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

    private function submissionFilesPath(Submission $submission, ?int $fileId = null): string
    {
        $submission->loadMissing('deliverable.phase.period', 'project');

        $periodId = $submission->deliverable->phase?->period?->id;
        $phaseId = $submission->deliverable->phase?->id;
        $deliverableId = $submission->deliverable_id;
        $projectId = $submission->project_id;

        $base = "/api/pg/academic-periods/{$periodId}/phases/{$phaseId}/deliverables/{$deliverableId}/projects/{$projectId}/submissions/{$submission->id}/files";

        return $fileId === null ? $base : $base.'/'.$fileId;
    }
}
