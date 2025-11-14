<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Phase;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $response = $this->putJson("/api/pg/phases/{$this->phase->id}", [
            'start_date' => '2025-02-01',
            'end_date' => '2025-04-30',
        ]);

        $response->assertStatus(200);
        $this->phase->refresh();
        $this->assertEquals('2025-02-01', $this->phase->start_date->format('Y-m-d'));
        $this->assertEquals('2025-04-30', $this->phase->end_date->format('Y-m-d'));
    }

    public function test_start_date_cannot_be_before_period_start_date(): void
    {
        $response = $this->putJson("/api/pg/phases/{$this->phase->id}", [
            'start_date' => '2024-12-31',
            'end_date' => '2025-03-31',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('start_date');
        $this->assertStringContainsString('before the academic period start date', $response->json('errors.start_date.0'));
    }

    public function test_end_date_cannot_be_after_period_end_date(): void
    {
        $response = $this->putJson("/api/pg/phases/{$this->phase->id}", [
            'start_date' => '2025-01-15',
            'end_date' => '2025-07-01',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('end_date');
        $this->assertStringContainsString('after the academic period end date', $response->json('errors.end_date.0'));
    }

    public function test_end_date_must_be_after_or_equal_start_date(): void
    {
        $response = $this->putJson("/api/pg/phases/{$this->phase->id}", [
            'start_date' => '2025-03-15',
            'end_date' => '2025-03-01',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('end_date');
        $this->assertStringContainsString('after the start date', $response->json('errors.end_date.0'));
    }

    public function test_dates_cannot_overlap_with_other_phases(): void
    {
        // Create another phase in the same period
        Phase::factory()->create([
            'period_id' => $this->period->id,
            'start_date' => '2025-04-01',
            'end_date' => '2025-06-30',
        ]);

        // Try to update the first phase to overlap with the second
        $response = $this->putJson("/api/pg/phases/{$this->phase->id}", [
            'start_date' => '2025-03-15',
            'end_date' => '2025-04-15',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('start_date');
        $this->assertStringContainsString('overlap with another phase', $response->json('errors.start_date.0'));
    }

    public function test_dates_cannot_completely_contain_another_phase(): void
    {
        // Create another phase in the same period
        Phase::factory()->create([
            'period_id' => $this->period->id,
            'start_date' => '2025-04-01',
            'end_date' => '2025-05-31',
        ]);

        // Try to update the first phase to completely contain the second
        $response = $this->putJson("/api/pg/phases/{$this->phase->id}", [
            'start_date' => '2025-03-01',
            'end_date' => '2025-06-30',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('start_date');
        $this->assertStringContainsString('overlap with another phase', $response->json('errors.start_date.0'));
    }

    public function test_can_update_only_name(): void
    {
        $response = $this->putJson("/api/pg/phases/{$this->phase->id}", [
            'name' => 'Updated Phase Name',
        ]);

        $response->assertStatus(200);
        $this->phase->refresh();
        $this->assertEquals('Updated Phase Name', $this->phase->name);
        $this->assertEquals('2025-01-15', $this->phase->start_date->format('Y-m-d'));
        $this->assertEquals('2025-03-31', $this->phase->end_date->format('Y-m-d'));
    }

    public function test_can_update_only_start_date(): void
    {
        $response = $this->putJson("/api/pg/phases/{$this->phase->id}", [
            'start_date' => '2025-02-01',
        ]);

        $response->assertStatus(200);
        $this->phase->refresh();
        $this->assertEquals('2025-02-01', $this->phase->start_date->format('Y-m-d'));
        $this->assertEquals('2025-03-31', $this->phase->end_date->format('Y-m-d'));
    }

    public function test_dates_on_period_boundaries_are_valid(): void
    {
        $response = $this->putJson("/api/pg/phases/{$this->phase->id}", [
            'start_date' => '2025-01-01',
            'end_date' => '2025-06-30',
        ]);

        $response->assertStatus(200);
        $this->phase->refresh();
        $this->assertEquals('2025-01-01', $this->phase->start_date->format('Y-m-d'));
        $this->assertEquals('2025-06-30', $this->phase->end_date->format('Y-m-d'));
    }

    public function test_adjacent_phases_do_not_overlap(): void
    {
        // Create another phase in the same period
        Phase::factory()->create([
            'period_id' => $this->period->id,
            'start_date' => '2025-04-01',
            'end_date' => '2025-06-30',
        ]);

        // Update the first phase to end just before the second starts
        $response = $this->putJson("/api/pg/phases/{$this->phase->id}", [
            'start_date' => '2025-01-15',
            'end_date' => '2025-03-31',
        ]);

        $response->assertStatus(200);
    }

    public function test_invalid_date_format_is_rejected(): void
    {
        $response = $this->putJson("/api/pg/phases/{$this->phase->id}", [
            'start_date' => '15/01/2025',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('start_date');
    }
}
