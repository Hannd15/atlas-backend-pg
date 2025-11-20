<?php

namespace Database\Factories;

use App\Models\ProjectStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Get or create the default "Activo" status
        $defaultStatus = ProjectStatus::firstOrCreate(
            ['name' => 'Activo'],
        );

        return [
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'status_id' => $defaultStatus->id,
            'thematic_line_id' => null,
        ];
    }
}
