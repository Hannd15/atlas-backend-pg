<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\AcademicPeriod;
use App\Models\AcademicPeriodState;
use App\Models\File;
use App\Models\Phase;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Proposal;
use App\Models\ProposalStatus;
use App\Models\ProposalType;
use App\Models\ThematicLine;
use App\Models\User;
use App\Services\ApprovalRequestActionRunner;
use App\Services\ApprovalRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        Proposal::query()->delete();
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

    public function test_teacher_proposal_requires_committee_approval(): void
    {
        $statusId = $this->proposalStatusId('pending');
        $line = ThematicLine::create(['name' => 'Línea Investigación']);
        $proposer = User::factory()->create();
        $director = User::factory()->create();
        $committeeA = User::factory()->create();
        $committeeB = User::factory()->create();

        $this->atlasUserServiceFake->setUserRoles([
            $committeeA->id => ['parte del comité de proyectos de grado'],
            $committeeB->id => ['parte del comité de proyectos de grado'],
        ]);

        $this->fakeAuth(['id' => $proposer->id, 'roles' => ['Director']]);

        $response = $this->postJson('/api/pg/proposals', [
            'title' => 'Nueva propuesta docente',
            'description' => 'Descripción detallada',
            'thematic_line_id' => $line->id,
            'preferred_director_id' => $director->id,
            'proposal_status_id' => $statusId,
        ], $this->authHeaders());

        $response->assertStatus(202)
            ->assertJsonStructure(['message', 'approval_request_id', 'status', 'title', 'recipient_ids']);

        $this->assertDatabaseCount('proposals', 0);

        $approvalRequest = ApprovalRequest::with('recipients')
            ->findOrFail($response->json('approval_request_id'));

        $this->assertSame('proposal.committee', $approvalRequest->action_key);
        $this->assertEqualsCanonicalizing([
            $committeeA->id,
            $committeeB->id,
        ], $approvalRequest->recipients->pluck('user_id')->all());

        $this->approveRequest($approvalRequest, [$committeeA->id, $committeeB->id]);

        $proposal = Proposal::with('type')->latest('id')->first();

        $this->assertNotNull($proposal);
        $this->assertSame('made_by_teacher', $proposal->type->code);
        $this->assertSame($proposer->id, $proposal->proposer_id);
        $this->assertSame($director->id, $proposal->preferred_director_id);
    }

    public function test_student_proposal_requires_director_then_committee_approval_and_creates_project(): void
    {
        $statusId = $this->proposalStatusId('pending');
        $line = ThematicLine::create(['name' => 'Línea Formación']);
        $student = User::factory()->create();
        $director = User::factory()->create();
        $committeeA = User::factory()->create();
        $committeeB = User::factory()->create();

        $targetPeriod = $this->targetAcademicPeriod();

        Phase::create([
            'period_id' => $targetPeriod->id,
            'name' => 'Segunda fase activa',
            'start_date' => now()->addWeek(),
            'end_date' => now()->addWeeks(2),
        ]);

        $expectedPhaseId = Phase::query()
            ->where('period_id', $targetPeriod->id)
            ->orderBy('id')
            ->value('id');

        $this->atlasUserServiceFake->setUserRoles([
            $committeeA->id => ['parte del comité de proyectos de grado'],
            $committeeB->id => ['parte del comité de proyectos de grado'],
        ]);

        $this->fakeAuth(['id' => $student->id, 'roles' => ['Estudiante']]);

        $response = $this->postJson('/api/pg/proposals', [
            'title' => 'Propuesta estudiantil',
            'description' => 'Descripción',
            'thematic_line_id' => $line->id,
            'preferred_director_id' => $director->id,
            'proposal_status_id' => $statusId,
        ], $this->authHeaders());

        $response->assertStatus(202);

        $directorRequest = ApprovalRequest::with('recipients')
            ->findOrFail($response->json('approval_request_id'));
        $this->assertSame('proposal.student.director', $directorRequest->action_key);
        $this->assertEquals([$director->id], $directorRequest->recipients->pluck('user_id')->all());

        $this->approveRequest($directorRequest, [$director->id]);

        $committeeRequest = ApprovalRequest::with('recipients')
            ->where('action_key', 'proposal.committee')
            ->latest('id')
            ->firstOrFail();
        $this->assertEqualsCanonicalizing([
            $committeeA->id,
            $committeeB->id,
        ], $committeeRequest->recipients->pluck('user_id')->all());

        $this->approveRequest($committeeRequest, [$committeeA->id, $committeeB->id]);

        $proposal = Proposal::with('type')->latest('id')->first();

        $this->assertNotNull($proposal);
        $this->assertSame('made_by_student', $proposal->type->code);
        $this->assertSame($director->id, $proposal->preferred_director_id);

        $project = Project::where('proposal_id', $proposal->id)->first();

        $this->assertNotNull($project);
        $this->assertSame($proposal->title, $project->title);
        $this->assertSame($proposal->description, $project->description);
        $this->assertSame($proposal->thematic_line_id, $project->thematic_line_id);

        $enProcesoId = ProjectStatus::query()->where('name', 'En proceso')->value('id');

        $this->assertSame($enProcesoId, $project->status_id);
        $this->assertSame($expectedPhaseId, $project->phase_id);
    }

    public function test_student_proposal_requires_preferred_director(): void
    {
        $statusId = $this->proposalStatusId('pending');
        $line = ThematicLine::create(['name' => 'Línea Formación']);
        $student = User::factory()->create();

        $this->fakeAuth(['id' => $student->id, 'roles' => ['Estudiante']]);

        $this->postJson('/api/pg/proposals', [
            'title' => 'Propuesta sin director',
            'description' => 'Descripción',
            'thematic_line_id' => $line->id,
            'preferred_director_id' => null,
            'proposal_status_id' => $statusId,
        ], $this->authHeaders())
            ->assertStatus(422)
            ->assertJson(['message' => 'Las propuestas de estudiantes requieren un director preferido.']);
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
            'thematic_line_id' => $proposal->thematic_line_id,
            'preferred_director_id' => $proposal->preferred_director_id,
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
        $this->withHeaders(['Authorization' => ''])
            ->getJson('/api/pg/proposals')
            ->assertStatus(401)
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_proposals_route_forwards_auth_forbidden_response(): void
    {
        $this->failNextAtlasAuth(403, ['message' => 'Missing permissions.']);

        $this->getJson('/api/pg/proposals', $this->authHeaders())
            ->assertStatus(403)
            ->assertExactJson(['message' => 'Missing permissions.']);
    }

    public function test_proposals_route_returns_service_unavailable_when_user_payload_missing(): void
    {
        $this->failNextAtlasAuth(503, ['message' => 'Authentication service unavailable.']);

        $this->getJson('/api/pg/proposals', $this->authHeaders())
            ->assertStatus(503)
            ->assertExactJson(['message' => 'Authentication service unavailable.']);
    }

    protected function fakeAuth(array $userPayload): void
    {
        $this->mockAtlasUser($userPayload);
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

    protected function approveRequest(ApprovalRequest $approvalRequest, array $userIds): void
    {
        $service = app(ApprovalRequestService::class);

        foreach ($userIds as $userId) {
            $approvalRequest = $service->recordDecision(
                $approvalRequest,
                $userId,
                ApprovalRequest::DECISION_APPROVED
            );

            if ($approvalRequest->status !== ApprovalRequest::STATUS_PENDING) {
                app(ApprovalRequestActionRunner::class)->run(
                    $approvalRequest->fresh(),
                    $approvalRequest->resolved_decision
                );

                break;
            }
        }
    }

    protected function targetAcademicPeriod(): AcademicPeriod
    {
        $activeStateId = AcademicPeriodState::activeId();

        $period = AcademicPeriod::query()
            ->where('state_id', $activeStateId)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->orderBy('start_date')
            ->orderBy('id')
            ->first();

        if (! $period) {
            $period = AcademicPeriod::query()
                ->where('state_id', $activeStateId)
                ->orderBy('start_date')
                ->orderBy('id')
                ->firstOrFail();
        }

        return $period;
    }
}
