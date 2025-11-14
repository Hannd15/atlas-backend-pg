<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Deliverable;
use App\Models\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeliverableFileEndpointsTest extends TestCase
{
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

    public function test_index_returns_files_for_deliverable(): void
    {
        [$deliverable, $file] = $this->createDeliverableAndFile();

        // Associate file with deliverable
        $deliverable->files()->attach($file);

        $response = $this->getJson("/api/pg/deliverables/{$deliverable->id}/files");

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $file->id)
            ->assertJsonPath('0.name', $file->name)
            ->assertJsonPath('0.deliverable_id', $deliverable->id);
    }

    public function test_store_uploads_file_and_associates(): void
    {
        $deliverable = $this->createDeliverable();

        $uploaded = UploadedFile::fake()->create('propuesta.pdf', 100, 'application/pdf');

        $response = $this->postJson("/api/pg/deliverables/{$deliverable->id}/files", [
            'file' => $uploaded,
            'name' => 'Propuesta Técnica',
        ]);

        $response->assertCreated()
            ->assertJsonPath('deliverable_id', $deliverable->id)
            ->assertJsonPath('name', 'Propuesta Técnica')
            ->assertJsonPath('extension', 'pdf');

        $fileId = $response->json('id');
        $file = File::find($fileId);

        $this->assertTrue($deliverable->files()->where('file_id', $fileId)->exists());
        $this->assertTrue(Storage::disk($file->disk)->exists($file->path));
    }

    /**
     * @return array{0: Deliverable, 1: File}
     */
    private function createDeliverableAndFile(): array
    {
        $deliverable = $this->createDeliverable();

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

    private function createDeliverable(): Deliverable
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

        return $phase->deliverables()->create([
            'name' => 'Entrega 1',
            'description' => 'Documento',
            'due_date' => '2025-04-15 18:00:00',
        ]);
    }
}
