<?php

namespace Tests;

use App\Models\AcademicPeriod;
use App\Models\AcademicPeriodState;
use App\Models\Phase;
use App\Services\AtlasUserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Date;
use Tests\Fakes\FakeAtlasUserService;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;

    protected $defaultHeaders = [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer test-token',
    ];

    protected FakeAtlasUserService $atlasUserServiceFake;

    protected bool $ensureActivePhase = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->atlasUserServiceFake = new FakeAtlasUserService;
        $this->app->instance(AtlasUserService::class, $this->atlasUserServiceFake);

        if ($this->ensureActivePhase) {
            $this->ensureActivePhaseExists();
        }
    }

    protected function ensureActivePhaseExists(): void
    {
        $activeStateId = AcademicPeriodState::activeId();

        $phaseExists = Phase::query()
            ->whereHas('period', fn ($query) => $query->where('state_id', $activeStateId))
            ->exists();

        if ($phaseExists) {
            return;
        }

        $period = AcademicPeriod::factory()->create([
            'start_date' => Date::now()->subMonths(2),
            'end_date' => Date::now()->addMonths(2),
        ]);

        Phase::factory()->create([
            'period_id' => $period->id,
            'start_date' => Date::now()->subMonth(),
            'end_date' => Date::now()->addMonth(),
            'name' => 'PG Active',
        ]);
    }
}
