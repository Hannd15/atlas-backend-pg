<?php

namespace Tests\Feature;

use App\Models\Proposal;
use App\Models\ProposalStatus;
use App\Models\ProposalType;
use App\Models\ThematicLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProposalFilesEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function createProposal(): Proposal
    {
        $proposer = User::factory()->create();
        $line = ThematicLine::firstOrCreate(['name' => 'IoT']);
        $type = ProposalType::firstOrCreate(['code' => 'made_by_teacher'], ['name' => 'Docente']);
        $status = ProposalStatus::firstOrCreate(['code' => 'pending'], ['name' => 'Pendiente']);

        return Proposal::create([
            'title' => 'Sistema de monitoreo',
            'description' => 'Descripcion corta',
            'proposal_type_id' => $type->id,
            'proposal_status_id' => $status->id,
            'proposer_id' => $proposer->id,
            'preferred_director_id' => null,
            'thematic_line_id' => $line->id,
        ]);
    }

    public function test_upload_and_list_proposal_files(): void
    {
        $proposal = $this->createProposal();
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->create('resumen.pdf', 10, 'application/pdf');

        $this->postJson("/api/pg/proposals/{$proposal->id}/files", [
            'file' => $file,
        ])->assertCreated()->assertJsonStructure(['id', 'name', 'extension', 'url']);

        $this->getJson("/api/pg/proposals/{$proposal->id}/files")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'resumen.pdf']);
    }
}
