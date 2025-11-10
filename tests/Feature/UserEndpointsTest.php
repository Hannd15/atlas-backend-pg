<?php

namespace Tests\Feature;

use App\Models\ProjectPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\PgApiResponseHelpers;
use Tests\TestCase;

class UserEndpointsTest extends TestCase
{
    use PgApiResponseHelpers;
    use RefreshDatabase;

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
        $director = ProjectPosition::create(['name' => 'Director']);
        $jurado = ProjectPosition::create(['name' => 'Jurado']);

        $userA = User::factory()->create(['name' => 'Alice Example', 'email' => 'alice@example.com']);
        $userA->eligiblePositions()->sync([$director->id, $jurado->id]);

        Carbon::setTestNow('2025-03-02 16:00:00');
        $userB = User::factory()->create(['name' => 'Bob Example', 'email' => 'bob@example.com']);
        $userB->eligiblePositions()->sync([$jurado->id]);

        $response = $this->getJson('/api/pg/users');

        $users = User::with('eligiblePositions')->orderBy('updated_at', 'desc')->get();

        $response->assertOk()->assertExactJson($this->userIndexArray($users));
    }

    public function test_show_returns_expected_resource(): void
    {
        $director = ProjectPosition::create(['name' => 'Director']);

        $user = User::factory()->create(['name' => 'Alice Example', 'email' => 'alice@example.com']);
        $user->eligiblePositions()->sync([$director->id]);

        $response = $this->getJson("/api/pg/users/{$user->id}");

        $user->load('eligiblePositions');

        $response->assertOk()->assertExactJson($this->userShowResource($user));
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

        $users = User::with('eligiblePositions')->orderBy('name')->get();

        $this->getJson('/api/pg/users/dropdown')
            ->assertOk()
            ->assertExactJson($this->userDropdownArray($users));
    }
}
