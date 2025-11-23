<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Phase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UpdatePhaseWithDatesTest extends TestCase
{
    use RefreshDatabase;

    private AcademicPeriod $period;

    private Phase $phase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->period = AcademicPeriod::factory()->create([
            'start_date' => '2025-01-01',
            'end_date' => '2025-06-30',
        ]);

        $this->phase = Phase::factory()->create([
            'period_id' => $this->period->id,
            'start_date' => '2025-01-15',
            'end_date' => '2025-03-31',
        ]);
    }

    public function test_can_update_phase_with_valid_dates(): void
    {
        $response = $this->putJson($this->phaseRoute(), [
            'start_date' => '2025-02-01',
            'end_date' => '2025-04-30',
        ]);

        $response->assertStatus(200);
        $this->phase->refresh();
        $this->assertEquals('2025-02-01', Carbon::parse($this->phase->start_date)->toDateString());
        $this->assertEquals('2025-04-30', Carbon::parse($this->phase->end_date)->toDateString());
    }

    public function test_start_date_cannot_be_before_period_start_date(): void
    {
        $response = $this->putJson($this->phaseRoute(), [
            'start_date' => '2024-12-31',
            'end_date' => '2025-03-31',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('start_date');
        $this->assertStringContainsString('before the academic period start date', $response->json('errors.start_date.0'));
    }

    public function test_end_date_cannot_be_after_period_end_date(): void
    {
        $response = $this->putJson($this->phaseRoute(), [
            'start_date' => '2025-01-15',
            'end_date' => '2025-07-01',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('end_date');
        $this->assertStringContainsString('after the academic period end date', $response->json('errors.end_date.0'));
    }

    public function test_end_date_must_be_after_or_equal_start_date(): void
    {
        $response = $this->putJson($this->phaseRoute(), [
            'start_date' => '2025-03-15',
            'end_date' => '2025-03-01',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('end_date');
        $this->assertStringContainsString('after the start date', $response->json('errors.end_date.0'));
    }

    public function test_dates_can_overlap_with_other_phases(): void
    {
        Phase::factory()->create([
            'period_id' => $this->period->id,
            'start_date' => '2025-04-01',
            'end_date' => '2025-06-30',
        ]);

        $response = $this->putJson($this->phaseRoute(), [
            'start_date' => '2025-03-15',
            'end_date' => '2025-04-15',
        ]);

        $response->assertStatus(200);
        $this->phase->refresh();
        $this->assertEquals('2025-03-15', Carbon::parse($this->phase->start_date)->toDateString());
        $this->assertEquals('2025-04-15', Carbon::parse($this->phase->end_date)->toDateString());
    }

    public function test_dates_can_completely_cover_another_phase(): void
    {
        Phase::factory()->create([
            'period_id' => $this->period->id,
            'start_date' => '2025-04-01',
            'end_date' => '2025-05-31',
        ]);

        $response = $this->putJson($this->phaseRoute(), [
            'start_date' => '2025-03-01',
            'end_date' => '2025-06-30',
        ]);

        $response->assertStatus(200);
        $this->phase->refresh();
        $this->assertEquals('2025-03-01', Carbon::parse($this->phase->start_date)->toDateString());
        $this->assertEquals('2025-06-30', Carbon::parse($this->phase->end_date)->toDateString());
    }

    public function test_can_update_only_name(): void
    {
        $response = $this->putJson($this->phaseRoute(), [
            'name' => 'Updated Phase Name',
        ]);

        $response->assertStatus(200);
        $this->phase->refresh();
        $this->assertEquals('Updated Phase Name', $this->phase->name);
        $this->assertEquals('2025-01-15', Carbon::parse($this->phase->start_date)->toDateString());
        $this->assertEquals('2025-03-31', Carbon::parse($this->phase->end_date)->toDateString());
    }

    public function test_can_update_only_start_date(): void
    {
        $response = $this->putJson($this->phaseRoute(), [
            'start_date' => '2025-02-01',
        ]);

        $response->assertStatus(200);
        $this->phase->refresh();
        $this->assertEquals('2025-02-01', Carbon::parse($this->phase->start_date)->toDateString());
        $this->assertEquals('2025-03-31', Carbon::parse($this->phase->end_date)->toDateString());
    }

    public function test_dates_on_period_boundaries_are_valid(): void
    {
        $response = $this->putJson($this->phaseRoute(), [
            'start_date' => '2025-01-01',
            'end_date' => '2025-06-30',
        ]);

        $response->assertStatus(200);
        $this->phase->refresh();
        $this->assertEquals('2025-01-01', Carbon::parse($this->phase->start_date)->toDateString());
        $this->assertEquals('2025-06-30', Carbon::parse($this->phase->end_date)->toDateString());
    }

    public function test_adjacent_phases_do_not_overlap(): void
    {
        Phase::factory()->create([
            'period_id' => $this->period->id,
            'start_date' => '2025-04-01',
            'end_date' => '2025-06-30',
        ]);

        $response = $this->putJson($this->phaseRoute(), [
            'start_date' => '2025-01-15',
            'end_date' => '2025-03-31',
        ]);

        $response->assertStatus(200);
    }

    public function test_invalid_date_format_is_rejected(): void
    {
        $response = $this->putJson($this->phaseRoute(), [
            'start_date' => '15/01/2025',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('start_date');
    }

    private function phaseRoute(): string
    {
        return "/api/pg/academic-periods/{$this->period->id}/phases/{$this->phase->id}";
    }
}
