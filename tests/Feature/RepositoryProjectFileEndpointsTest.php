<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\RepositoryProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RepositoryProjectFileEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2025-04-01 10:00:00');
        Storage::fake('public');
        config(['filesystems.default' => 'public']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_get_all_returns_files_with_project_context(): void
    {
        [$repositoryProject, $file] = $this->createRepositoryProjectWithFile();

        $response = $this->getJson('/api/pg/repository-project-files');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $file->id)
            ->assertJsonPath('0.name', $file->name)
            ->assertJsonPath('0.repository_project_id', $repositoryProject->id)
            ->assertJsonPath('0.repository_project_name', $repositoryProject->title)
            ->assertJsonPath('0.repository_project_publish_date', $repositoryProject->publish_date->toDateString());
    }

    public function test_index_returns_files_for_repository_project(): void
    {
        [$repositoryProject, $file] = $this->createRepositoryProjectWithFile('avance.pdf');

        $response = $this->getJson("/api/pg/repository-projects/{$repositoryProject->id}/files");

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $file->id)
            ->assertJsonPath('0.name', 'avance.pdf')
            ->assertJsonPath('0.repository_project_id', $repositoryProject->id);
    }

    public function test_store_uploads_file_and_creates_relationship(): void
    {
        $repositoryProject = $this->createRepositoryProject();

        $uploaded = UploadedFile::fake()->create('memoria-final.pdf', 180, 'application/pdf');

        $response = $this->postJson("/api/pg/repository-projects/{$repositoryProject->id}/files", [
            'file' => $uploaded,
            'name' => 'Memoria Final',
        ]);

        $response->assertCreated()
            ->assertJsonPath('repository_project_id', $repositoryProject->id)
            ->assertJsonPath('name', 'Memoria Final');

        $fileId = $response->json('id');
        $file = File::find($fileId);

        $this->assertNotNull($file);
        $this->assertTrue(Storage::disk($file->disk)->exists($file->path));
        $this->assertTrue($repositoryProject->files()->where('file_id', $fileId)->exists());
    }

    /**
     * @return array{0: RepositoryProject, 1: File}
     */
    private function createRepositoryProjectWithFile(string $fileName = 'memoria.pdf'): array
    {
        $repositoryProject = $this->createRepositoryProject();

        $file = File::create([
            'name' => $fileName,
            'extension' => pathinfo($fileName, PATHINFO_EXTENSION),
            'url' => "https://files.test/{$fileName}",
            'disk' => 'public',
            'path' => "pg/uploads/{$fileName}",
        ]);

        Storage::disk('public')->put($file->path, 'contenido');

        $repositoryProject->files()->attach($file->id);

        return [$repositoryProject->fresh('files'), $file];
    }

    private function createRepositoryProject(): RepositoryProject
    {
        return RepositoryProject::create([
            'title' => 'Repositorio IoT',
            'description' => 'Investigaciones de IoT',
            'publish_date' => Carbon::parse('2025-04-01'),
            'keywords_es' => 'IoT, sensores',
            'keywords_en' => 'IoT, sensors',
            'abstract_es' => 'Resumen del proyecto',
            'abstract_en' => 'Project abstract',
        ]);
    }
}
