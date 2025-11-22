<?php

namespace Tests\Feature;

use App\Models\ProjectPosition;
use App\Models\Proposal;
use App\Models\ProposalStatus;
use App\Models\ProposalType;
use App\Models\ThematicLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\PgApiResponseHelpers;
use Tests\TestCase;

class UserEndpointsTest extends TestCase
{
    use PgApiResponseHelpers;
    use RefreshDatabase;

    protected bool $seed = false;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2025-03-01 16:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_index_returns_expected_payload(): void
    {
        $line = ThematicLine::create(['name' => 'Investigación']);
        $teacherType = ProposalType::firstOrCreate(['code' => 'made_by_teacher'], ['name' => 'Made by teacher']);
        $studentType = ProposalType::firstOrCreate(['code' => 'made_by_student'], ['name' => 'Made by student']);
        $pendingStatus = ProposalStatus::firstOrCreate(['code' => 'pending'], ['name' => 'Pending']);

        $director = ProjectPosition::create(['name' => 'Director']);
        $jurado = ProjectPosition::create(['name' => 'Jurado']);

        $userA = User::factory()->create(['name' => 'Alice Example', 'email' => 'alice@example.com']);
        $userA->eligiblePositions()->sync([$director->id, $jurado->id]);

        Proposal::create([
            'title' => 'Propuesta Docente',
            'description' => 'Contenido',
            'proposal_type_id' => $teacherType->id,
            'proposal_status_id' => $pendingStatus->id,
            'proposer_id' => $userA->id,
            'preferred_director_id' => null,
            'thematic_line_id' => $line->id,
        ]);

        Carbon::setTestNow('2025-03-02 16:00:00');
        $userB = User::factory()->create(['name' => 'Bob Example', 'email' => 'bob@example.com']);
        $userB->eligiblePositions()->sync([$jurado->id]);

        Proposal::create([
            'title' => 'Propuesta Estudiantil',
            'description' => 'Descripción',
            'proposal_type_id' => $studentType->id,
            'proposal_status_id' => $pendingStatus->id,
            'proposer_id' => $userB->id,
            'preferred_director_id' => $userA->id,
            'thematic_line_id' => $line->id,
        ]);

        $response = $this->getJson('/api/pg/users');

        $users = User::with(['eligiblePositions', 'proposals', 'preferredProposals'])->orderBy('updated_at', 'desc')->get();

        $response->assertOk()->assertExactJson($this->userIndexArray($users));

        $primary = $users->firstWhere('id', $userA->id);
        $this->assertNotNull($primary);
        $this->assertSame('Propuesta Docente, Propuesta Estudiantil', $primary->proposal_names);
    }

    public function test_show_returns_expected_resource(): void
    {
        $line = ThematicLine::create(['name' => 'Gestión']);
        $teacherType = ProposalType::firstOrCreate(['code' => 'made_by_teacher'], ['name' => 'Made by teacher']);
        $approvedStatus = ProposalStatus::firstOrCreate(['code' => 'approved'], ['name' => 'Approved']);

        $director = ProjectPosition::create(['name' => 'Director']);

        $user = User::factory()->create(['name' => 'Alice Example', 'email' => 'alice@example.com']);
        $user->eligiblePositions()->sync([$director->id]);

        Proposal::create([
            'title' => 'Propuesta Principal',
            'description' => 'Contenido',
            'proposal_type_id' => $teacherType->id,
            'proposal_status_id' => $approvedStatus->id,
            'proposer_id' => $user->id,
            'preferred_director_id' => null,
            'thematic_line_id' => $line->id,
        ]);

        $response = $this->getJson("/api/pg/users/{$user->id}");

        $user->load('eligiblePositions', 'proposals', 'preferredProposals');

        $response->assertOk()->assertExactJson($this->userShowResource($user));
        $this->assertSame('Propuesta Principal', $user->proposal_names);
    }

    public function test_update_updates_fields_and_syncs_positions(): void
    {
        $director = ProjectPosition::create(['name' => 'Director']);
        $jurado = ProjectPosition::create(['name' => 'Jurado']);

        $user = User::factory()->create(['name' => 'Alice Example', 'email' => 'alice@example.com']);
        $user->eligiblePositions()->sync([$director->id]);

        $payload = [
            'name' => 'Alice Updated',
            'email' => 'alice.updated@example.com',
            'project_position_eligibility_ids' => [$jurado->id],
        ];

        $response = $this->putJson("/api/pg/users/{$user->id}", $payload);

        $user->refresh()->load('eligiblePositions');

        $response->assertOk()->assertExactJson($this->userShowResource($user));

        $this->assertSame('Alice Updated', $user->name);
        $this->assertSame('alice.updated@example.com', $user->email);
        $this->assertEquals([$jurado->id], $user->eligiblePositions->pluck('id')->all());
    }

    public function test_dropdown_returns_expected_payload(): void
    {
        $director = ProjectPosition::create(['name' => 'Director']);
        $jurado = ProjectPosition::create(['name' => 'Jurado']);

        $userA = User::factory()->create(['name' => 'Alice Example', 'email' => 'alice@example.com']);
        $userA->eligiblePositions()->sync([$director->id]);

        $userB = User::factory()->create(['name' => 'Bob Example', 'email' => 'bob@example.com']);
        $userB->eligiblePositions()->sync([$jurado->id]);

        $line = ThematicLine::create(['name' => 'Formación']);
        $teacherType = ProposalType::firstOrCreate(['code' => 'made_by_teacher'], ['name' => 'Made by teacher']);
        $pendingStatus = ProposalStatus::firstOrCreate(['code' => 'pending'], ['name' => 'Pending']);

        Proposal::create([
            'title' => 'Propuesta Jurado',
            'proposal_type_id' => $teacherType->id,
            'proposal_status_id' => $pendingStatus->id,
            'proposer_id' => $userB->id,
            'preferred_director_id' => null,
            'thematic_line_id' => $line->id,
        ]);

        $users = User::with(['eligiblePositions', 'proposals', 'preferredProposals'])->orderBy('name')->get();

        $this->getJson('/api/pg/users/dropdown')
            ->assertOk()
            ->assertExactJson($this->userDropdownArray($users));

        $dropdownUser = collect($this->userDropdownArray($users))->firstWhere('value', $userB->id);
        $this->assertNotNull($dropdownUser);
        $this->assertSame('Propuesta Jurado', $dropdownUser['proposal_names']);
    }

    public function test_students_endpoint_returns_only_student_role_users(): void
    {
        $student = User::factory()->create(['name' => 'Student Example', 'email' => 'student@example.com']);
        $teacher = User::factory()->create(['name' => 'Teacher Example', 'email' => 'teacher@example.com']);

        $this->atlasUserServiceFake->setUserRoles([
            $student->id => ['Estudiante'],
            $teacher->id => ['Docente'],
        ]);

        $response = $this->getJson('/api/pg/users/students/dropdown');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'value' => $student->id,
                'label' => 'Student Example',
                'email' => 'student@example.com',
                'roles_list' => 'Estudiante',
            ])
            ->assertJsonMissing(['value' => $teacher->id]);
    }
}
