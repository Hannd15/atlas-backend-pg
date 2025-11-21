<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Proposal;
use App\Models\ProposalStatus;
use App\Models\ProposalType;
use App\Models\ThematicLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProposalEndpointsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<string, int>
     */
    protected array $proposalTypeIds = [];

    /**
     * @var array<string, int>
     */
    protected array $proposalStatusIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'filesystems.default' => 'public',
            'services.atlas_auth.url' => 'https://auth.example',
            'services.atlas_auth.timeout' => 5,
        ]);

        $this->proposalTypeIds = ProposalType::pluck('id', 'code')->map(fn ($id) => (int) $id)->all();
        $this->proposalStatusIds = ProposalStatus::pluck('id', 'code')->map(fn ($id) => (int) $id)->all();

        if (empty($this->proposalTypeIds) || empty($this->proposalStatusIds)) {
            $this->fail('Missing proposal reference data.');
        }
    }

    public function test_index_returns_only_teacher_proposals(): void
    {
        $teacherType = $this->proposalTypeId('made_by_teacher');
        $studentType = $this->proposalTypeId('made_by_student');
        $statusId = $this->proposalStatusId('pending');
        $line = ThematicLine::create(['name' => 'Línea A']);
        $proposer = User::factory()->create();
        $director = User::factory()->create();

        $teacherProposal = Proposal::create([
            'title' => 'Docencia avanzada',
            'description' => 'Propuesta dirigida por docente',
            'proposal_type_id' => $teacherType,
            'proposal_status_id' => $statusId,
            'proposer_id' => $proposer->id,
            'preferred_director_id' => $director->id,
            'thematic_line_id' => $line->id,
        ]);

        Proposal::create([
            'title' => 'Proyecto estudiantil',
            'description' => 'Propuesta enviada por estudiante',
            'proposal_type_id' => $studentType,
            'proposal_status_id' => $statusId,
            'proposer_id' => $proposer->id,
            'preferred_director_id' => $director->id,
            'thematic_line_id' => $line->id,
        ]);

        $this->fakeAuth(['id' => $director->id, 'roles' => ['Director']]);

        $response = $this->getJson('/api/pg/proposals', $this->authHeaders());

        $response->assertOk();
        $payload = $response->json();
        $this->assertCount(1, $payload);
        $this->assertSame($teacherProposal->id, $payload[0]['id']);
        $this->assertSame('Docencia avanzada', $payload[0]['title']);
    }

    public function test_store_creates_proposal_with_files_and_teacher_type(): void
    {
        $statusId = $this->proposalStatusId('pending');
        $line = ThematicLine::create(['name' => 'Línea Investigación']);
        $proposer = User::factory()->create();
        $director = User::factory()->create();

        $this->fakeAuth(['id' => $proposer->id, 'roles' => ['Director']]);

        $response = $this->postJson('/api/pg/proposals', [
            'title' => 'Nueva propuesta docente',
            'description' => 'Descripción detallada',
            'thematic_line_id' => $line->id,
            'preferred_director_id' => $director->id,
            'proposal_status_id' => $statusId,
        ], $this->authHeaders());

        $response->assertCreated();

        $proposal = Proposal::with('type')->firstOrFail();

        $this->assertSame('made_by_teacher', $proposal->type->code);
        $this->assertSame($proposer->id, $proposal->proposer_id);
        $this->assertSame($director->id, $proposal->preferred_director_id);

        $response->assertJsonFragment([
            'title' => 'Nueva propuesta docente',
            'proposer_name' => $proposer->name,
            'preferred_director_name' => $director->name,
        ]);
    }

    public function test_store_assigns_student_type_based_on_role(): void
    {
        $statusId = $this->proposalStatusId('pending');
        $line = ThematicLine::create(['name' => 'Línea Formación']);
        $proposer = User::factory()->create();

        $this->fakeAuth(['id' => $proposer->id, 'roles' => ['Estudiante']]);

        $response = $this->postJson('/api/pg/proposals', [
            'title' => 'Propuesta estudiantil',
            'description' => 'Descripción',
            'thematic_line_id' => $line->id,
            'preferred_director_id' => null,
            'proposal_status_id' => $statusId,
        ], $this->authHeaders());

        $response->assertCreated();

        $proposal = Proposal::with('type')->firstOrFail();
        $this->assertSame('made_by_student', $proposal->type->code);
    }

    public function test_update_modifies_core_fields(): void
    {
        $statusId = $this->proposalStatusId('pending');
        $line = ThematicLine::create(['name' => 'Línea Desarrollo']);
        $proposer = User::factory()->create();
        $director = User::factory()->create();

        $proposal = Proposal::create([
            'title' => 'Propuesta original',
            'description' => 'Contenido inicial',
            'proposal_type_id' => $this->proposalTypeId('made_by_teacher'),
            'proposal_status_id' => $statusId,
            'proposer_id' => $proposer->id,
            'preferred_director_id' => $director->id,
            'thematic_line_id' => $line->id,
        ]);

        $this->fakeAuth(['id' => $director->id, 'roles' => ['Director']]);

        $response = $this->putJson('/api/pg/proposals/'.$proposal->id, [
            'title' => 'Propuesta actualizada',
            'description' => 'Contenido actualizado',
            'proposal_status_id' => $this->proposalStatusId('approved'),
            'preferred_director_id' => null,
            'thematic_line_id' => $line->id,
        ], $this->authHeaders());

        $response->assertOk()->assertJsonFragment([
            'title' => 'Propuesta actualizada',
            'description' => 'Contenido actualizado',
        ]);

        $proposal->refresh();

        $this->assertSame('Propuesta actualizada', $proposal->title);
        $this->assertSame('Contenido actualizado', $proposal->description);
        $this->assertNull($proposal->preferred_director_id);
    }

    public function test_show_returns_expected_payload(): void
    {
        $proposal = $this->createProposalWithFiles();

        $this->fakeAuth(['id' => $proposal->proposer_id, 'roles' => ['Director']]);

        $response = $this->getJson('/api/pg/proposals/'.$proposal->id, $this->authHeaders());

        $response->assertOk()->assertJsonFragment([
            'id' => $proposal->id,
            'title' => $proposal->title,
            'proposer_name' => $proposal->proposer?->name,
            'preferred_director_name' => $proposal->preferredDirector?->name,
        ]);
    }

    public function test_destroy_deletes_proposal_record(): void
    {
        $proposal = $this->createProposalWithFiles();
        $file = $proposal->files->first();

        $this->fakeAuth(['id' => $proposal->proposer_id, 'roles' => ['Director']]);

        $this->deleteJson('/api/pg/proposals/'.$proposal->id, [], $this->authHeaders())
            ->assertOk()
            ->assertExactJson(['message' => 'Proposal deleted successfully']);

        $this->assertDatabaseMissing('proposals', ['id' => $proposal->id]);
        $this->assertDatabaseMissing('proposal_files', ['proposal_id' => $proposal->id]);
        $this->assertDatabaseHas('files', ['id' => $file->id]);
    }

    public function test_proposals_routes_require_bearer_token(): void
    {
        Http::fake();

        $this->withHeaders(['Authorization' => ''])
            ->getJson('/api/pg/proposals')
            ->assertStatus(401)
            ->assertExactJson(['message' => 'Unauthenticated.']);

        Http::assertNothingSent();
    }

    public function test_proposals_route_forwards_auth_forbidden_response(): void
    {
        app()->forgetInstance('testing.atlasUser');
        app()->instance('testing.atlasError', [
            'status' => 403,
            'body' => ['message' => 'Missing permissions.'],
        ]);

        $this->getJson('/api/pg/proposals', $this->authHeaders())
            ->assertStatus(403)
            ->assertExactJson(['message' => 'Missing permissions.']);

        app()->forgetInstance('testing.atlasError');
    }

    public function test_proposals_route_returns_service_unavailable_when_user_payload_missing(): void
    {
        app()->forgetInstance('testing.atlasUser');
        app()->instance('testing.atlasError', [
            'status' => 503,
            'body' => ['message' => 'Authentication service unavailable.'],
        ]);

        $this->getJson('/api/pg/proposals', $this->authHeaders())
            ->assertStatus(503)
            ->assertExactJson(['message' => 'Authentication service unavailable.']);

        app()->forgetInstance('testing.atlasError');
    }

    protected function fakeAuth(array $userPayload): void
    {
        Http::fake([
            'https://auth.example/api/auth/token/verify' => Http::response([
                'authorized' => true,
                'user' => $userPayload,
            ], 200),
        ]);

        app()->instance('testing.atlasUser', $userPayload);
        app()->forgetInstance('testing.atlasError');
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer valid-token'];
    }

    protected function proposalTypeId(string $code): int
    {
        if (! array_key_exists($code, $this->proposalTypeIds)) {
            $this->fail('Missing proposal type seed.');
        }

        return $this->proposalTypeIds[$code];
    }

    protected function proposalStatusId(string $statusCode): int
    {
        if (! array_key_exists($statusCode, $this->proposalStatusIds)) {
            $this->fail('Missing proposal status seed.');
        }

        return $this->proposalStatusIds[$statusCode];
    }

    protected function createProposalWithFiles(): Proposal
    {
        $statusId = $this->proposalStatusId('pending');
        $line = ThematicLine::create(['name' => 'Línea Integración']);
        $proposer = User::factory()->create();
        $director = User::factory()->create();

        $proposal = Proposal::create([
            'title' => 'Propuesta con anexos',
            'description' => 'Contenido',
            'proposal_type_id' => $this->proposalTypeId('made_by_teacher'),
            'proposal_status_id' => $statusId,
            'proposer_id' => $proposer->id,
            'preferred_director_id' => $director->id,
            'thematic_line_id' => $line->id,
        ]);

        $firstFile = File::create([
            'name' => 'anexo1.pdf',
            'extension' => 'pdf',
            'url' => 'https://files.test/anexo1.pdf',
            'disk' => 'public',
            'path' => 'pg/uploads/anexo1.pdf',
        ]);
        $secondFile = File::create([
            'name' => 'anexo2.pdf',
            'extension' => 'pdf',
            'url' => 'https://files.test/anexo2.pdf',
            'disk' => 'public',
            'path' => 'pg/uploads/anexo2.pdf',
        ]);

        $proposal->files()->attach([
            $firstFile->id => ['created_at' => now()->subMinutes(1), 'updated_at' => now()->subMinutes(1)],
            $secondFile->id => ['created_at' => now(), 'updated_at' => now()],
        ]);
        $proposal->load('files');

        return $proposal;
    }
}
