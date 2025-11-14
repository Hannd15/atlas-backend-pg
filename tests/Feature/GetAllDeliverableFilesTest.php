<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Deliverable;
use App\Models\File;
use App\Models\Phase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetAllDeliverableFilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_all_returns_all_deliverable_files_with_related_data(): void
    {
        // Create academic period
        $period = AcademicPeriod::factory()->create([
            'name' => '2025-1',
        ]);

        // Create phase
        $phase = Phase::factory()->create([
            'period_id' => $period->id,
            'name' => 'Fase 1',
        ]);

        // Create deliverable
        $deliverable = Deliverable::factory()->create([
            'phase_id' => $phase->id,
            'name' => 'Entrega 1',
        ]);

        // Create files
        $file1 = File::create([
            'name' => 'document1.pdf',
            'extension' => 'pdf',
            'url' => 'https://example.com/document1.pdf',
            'disk' => 'public',
            'path' => 'pg/uploads/document1.pdf',
        ]);

        $file2 = File::create([
            'name' => 'document2.pdf',
            'extension' => 'pdf',
            'url' => 'https://example.com/document2.pdf',
            'disk' => 'public',
            'path' => 'pg/uploads/document2.pdf',
        ]);

        // Associate files with deliverable
        $deliverable->files()->attach([$file1->id, $file2->id]);

        $response = $this->getJson('/api/pg/deliverable-files');

        $response->assertOk();
        $response->assertJsonCount(2);
        $response->assertJsonFragment([
            'id' => $file1->id,
            'file_name' => 'document1.pdf',
            'deliverable_name' => 'Entrega 1',
            'phase_name' => 'Fase 1',
            'period_name' => '2025-1',
        ]);
        $response->assertJsonFragment([
            'id' => $file2->id,
            'file_name' => 'document2.pdf',
            'deliverable_name' => 'Entrega 1',
            'phase_name' => 'Fase 1',
            'period_name' => '2025-1',
        ]);
    }

    public function test_get_all_returns_empty_array_when_no_files_exist(): void
    {
        $response = $this->getJson('/api/pg/deliverable-files');

        $response->assertOk();
        $response->assertExactJson([]);
    }

    public function test_get_all_returns_correct_structure_for_each_file(): void
    {
        $period = AcademicPeriod::factory()->create(['name' => '2025-2']);
        $phase = Phase::factory()->create(['period_id' => $period->id, 'name' => 'Fase 2']);
        $deliverable = Deliverable::factory()->create(['phase_id' => $phase->id, 'name' => 'Entrega 2']);

        $file = File::create([
            'name' => 'test.pdf',
            'extension' => 'pdf',
            'url' => 'https://example.com/test.pdf',
            'disk' => 'public',
            'path' => 'pg/uploads/test.pdf',
        ]);

        $deliverable->files()->attach($file->id);

        $response = $this->getJson('/api/pg/deliverable-files');

        $response->assertOk();
        $data = $response->json()[0];

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('file_name', $data);
        $this->assertArrayHasKey('deliverable_name', $data);
        $this->assertArrayHasKey('phase_name', $data);
        $this->assertArrayHasKey('period_name', $data);
        $this->assertCount(5, $data);
    }
}
