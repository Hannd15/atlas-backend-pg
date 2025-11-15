<?php

namespace Tests;

use App\Models\AcademicPeriod;
use App\Models\AcademicPeriodState;
use App\Models\Phase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Date;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureActivePhaseExists();
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
