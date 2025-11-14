<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Deliverable;
use App\Models\DeliverableFile;
use App\Models\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PgApiResponseHelpers;
use Tests\TestCase;

class DeliverableFileEndpointsTest extends TestCase
{
    use PgApiResponseHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2025-03-01 14:00:00');
        Storage::fake('public');
        config(['filesystems.default' => 'public']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_index_returns_expected_payload(): void
    {
        [$deliverable, $file] = $this->createDeliverableAndFile();

        DeliverableFile::create([
            'deliverable_id' => $deliverable->id,
            'file_id' => $file->id,
        ]);

        $response = $this->getJson('/api/pg/deliverable-files');

        $deliverableFiles = DeliverableFile::with('deliverable.phase.period', 'file')->orderByDesc('updated_at')->get();

        $response->assertOk()->assertExactJson($deliverableFiles->map(fn (DeliverableFile $item) => $this->deliverableFileResource($item))->values()->all());
    }

    public function test_store_creates_association(): void
    {
        [$deliverable, $file] = $this->createDeliverableAndFile();

        $response = $this->postJson('/api/pg/deliverable-files', [
            'deliverable_id' => $deliverable->id,
            'file_id' => $file->id,
        ]);

        $deliverableFile = DeliverableFile::firstOrFail()->load('deliverable.phase.period', 'file');

        $response->assertCreated()->assertExactJson($this->deliverableFileResource($deliverableFile));

        $this->assertDatabaseHas('deliverable_files', [
            'deliverable_id' => $deliverable->id,
            'file_id' => $file->id,
        ]);
    }

    public function test_show_returns_expected_resource(): void
    {
        [$deliverable, $file] = $this->createDeliverableAndFile();

        DeliverableFile::create([
            'deliverable_id' => $deliverable->id,
            'file_id' => $file->id,
        ]);

        $response = $this->getJson("/api/pg/deliverable-files/{$deliverable->id}/{$file->id}");

        $deliverableFile = DeliverableFile::first()->load('deliverable.phase.period', 'file');

        $response->assertOk()->assertExactJson($this->deliverableFileResource($deliverableFile));
    }

    public function test_destroy_deletes_association(): void
    {
        [$deliverable, $file] = $this->createDeliverableAndFile();

        DeliverableFile::create([
            'deliverable_id' => $deliverable->id,
            'file_id' => $file->id,
        ]);

        $this->deleteJson("/api/pg/deliverable-files/{$deliverable->id}/{$file->id}")
            ->assertOk()
            ->assertExactJson(['message' => 'Deliverable-file association deleted successfully']);

        $this->assertDatabaseMissing('deliverable_files', [
            'deliverable_id' => $deliverable->id,
            'file_id' => $file->id,
        ]);
    }

    public function test_update_allows_renaming_without_replacing_file(): void
    {
        [$deliverable, $file] = $this->createDeliverableAndFile();

        DeliverableFile::create([
            'deliverable_id' => $deliverable->id,
            'file_id' => $file->id,
        ]);

        $response = $this->putJson("/api/pg/deliverable-files/{$deliverable->id}/{$file->id}", [
            'name' => 'Documento Actualizado.pdf',
        ]);

        $deliverableFile = DeliverableFile::firstOrFail()->load('deliverable.phase.period', 'file');

        $response->assertOk()->assertExactJson($this->deliverableFileResource($deliverableFile));

        $this->assertSame('Documento Actualizado.pdf', $deliverableFile->file->name);
        $this->assertSame('pdf', $deliverableFile->file->extension);
        $this->assertTrue(Storage::disk('public')->exists($deliverableFile->file->path));
    }

    public function test_update_replaces_file_when_new_upload_provided(): void
    {
        [$deliverable, $file] = $this->createDeliverableAndFile();

        DeliverableFile::create([
            'deliverable_id' => $deliverable->id,
            'file_id' => $file->id,
        ]);

        $oldPath = $file->path;
        $this->assertTrue(Storage::disk('public')->exists($oldPath));

        $uploaded = UploadedFile::fake()->create('actualizado.docx', 120, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $response = $this->put("/api/pg/deliverable-files/{$deliverable->id}/{$file->id}", [
            'file' => $uploaded,
        ]);

        $deliverableFile = DeliverableFile::firstOrFail()->load('deliverable.phase.period', 'file');
        $updatedFile = $deliverableFile->file;

        $response->assertOk()->assertExactJson($this->deliverableFileResource($deliverableFile));

        $this->assertNotSame($oldPath, $updatedFile->path);
        $this->assertFalse(Storage::disk('public')->exists($oldPath));
        $this->assertTrue(Storage::disk($updatedFile->disk)->exists($updatedFile->path));
        $this->assertSame('actualizado.docx', $updatedFile->name);
        $this->assertSame('docx', $updatedFile->extension);
    }

    /**
     * @return array{0: Deliverable, 1: File}
     */
    private function createDeliverableAndFile(): array
    {
        $period = AcademicPeriod::create([
            'name' => '2025-1',
            'start_date' => '2025-01-01',
            'end_date' => '2025-06-30',
        ]);

        $phase = $period->phases()->create([
            'name' => 'PG I',
            'start_date' => '2025-01-01',
            'end_date' => '2025-06-30',
        ]);

        $deliverable = $phase->deliverables()->create([
            'name' => 'Entrega 1',
            'description' => 'Documento',
            'due_date' => '2025-04-15 18:00:00',
        ]);

        $file = File::create([
            'name' => 'propuesta.pdf',
            'extension' => 'pdf',
            'url' => 'https://files.test/propuesta.pdf',
            'disk' => 'public',
            'path' => 'pg/uploads/propuesta.pdf',
        ]);

        Storage::disk('public')->put($file->path, 'contenido');

        return [$deliverable, $file];
    }
}
