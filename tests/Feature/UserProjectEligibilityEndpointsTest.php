<?php

namespace Tests\Feature;

use App\Models\ProjectPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PgApiResponseHelpers;
use Tests\TestCase;

class UserProjectEligibilityEndpointsTest extends TestCase
{
    use PgApiResponseHelpers;
    use RefreshDatabase;

    protected bool $seed = false;

    public function test_by_user_returns_expected_summaries(): void
    {
        $this->createEligibilityFixtures();

        $this->getJson('/api/pg/user-project-eligibilities/by-user')
            ->assertOk()
            ->assertExactJson($this->userEligibilityByUserArray());
    }

    public function test_by_position_returns_expected_summaries(): void
    {
        $this->createEligibilityFixtures();

        $this->getJson('/api/pg/user-project-eligibilities/by-position')
            ->assertOk()
            ->assertExactJson($this->userEligibilityByPositionArray());
    }

    public function test_by_user_dropdown_returns_expected_payload(): void
    {
        $this->createEligibilityFixtures();

        $this->getJson('/api/pg/user-project-eligibilities/by-user/dropdown')
            ->assertOk()
            ->assertExactJson($this->userEligibilityByUserDropdownArray());
    }

    public function test_by_position_dropdown_returns_expected_payload(): void
    {
        $this->createEligibilityFixtures();

        $this->getJson('/api/pg/user-project-eligibilities/by-position/dropdown')
            ->assertOk()
            ->assertExactJson($this->userEligibilityByPositionDropdownArray());
    }

    public function test_directors_dropdown_returns_director_users(): void
    {
        $this->createEligibilityFixtures();

        $position = ProjectPosition::with(['eligibleUsers' => fn ($query) => $query->orderBy('name')])
            ->where('name', 'Director')
            ->first();

        $expected = $position?->eligibleUsers->map(fn (User $user) => [
            'value' => $user->id,
            'label' => $user->name,
        ])->values()->all() ?? [];

        $this->getJson('/api/pg/user-project-eligibilities/directors/dropdown')
            ->assertOk()
            ->assertExactJson($expected);
    }

    public function test_sync_position_users_replaces_existing_eligibilities(): void
    {
        $position = ProjectPosition::create(['name' => 'Director']);

        $alice = User::factory()->create(['name' => 'Alice Example']);
        $bob = User::factory()->create(['name' => 'Bob Example']);
        $charlie = User::factory()->create(['name' => 'Charlie Example']);

        $position->eligibleUsers()->sync([$alice->id]);

        $response = $this->postJson(
            "/api/pg/user-project-eligibilities/project-positions/{$position->id}/sync",
            [
                'user_ids' => [$bob->id, $charlie->id],
            ]
        );

        $response->assertOk()
            ->assertExactJson([
                'project_position_id' => $position->id,
                'project_position_name' => 'Director',
                'user_names' => 'Bob Example, Charlie Example',
            ]);

        $this->assertEqualsCanonicalizing(
            [$bob->id, $charlie->id],
            $position->fresh()->eligibleUsers->pluck('id')->all()
        );
    }

    private function createEligibilityFixtures(): void
    {
        $director = ProjectPosition::create(['name' => 'Director']);
        $evaluador = ProjectPosition::create(['name' => 'Evaluador']);
        $jurado = ProjectPosition::create(['name' => 'Jurado']);

        $alice = User::factory()->create([
            'name' => 'Alice Example',
            'email' => 'alice@example.com',
        ]);
        $bob = User::factory()->create([
            'name' => 'Bob Example',
            'email' => 'bob@example.com',
        ]);
        $charlie = User::factory()->create([
            'name' => 'Charlie Example',
            'email' => 'charlie@example.com',
        ]);

        $alice->eligiblePositions()->sync([$director->id, $jurado->id]);
        $bob->eligiblePositions()->sync([$director->id]);
        // Charlie intentionally has no eligibilities to validate empty labels remain intact

        // Ensure positions with no users remain represented
        $evaluador->eligibleUsers()->sync([]);
    }
}
