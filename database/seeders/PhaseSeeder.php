<?php

namespace Database\Seeders;

use App\Models\AcademicPeriod;
use Illuminate\Database\Seeder;

class PhaseSeeder extends Seeder
{
    public function run(): void
    {
        AcademicPeriod::query()->with('phases')->get()->each(function (AcademicPeriod $period): void {
            $period->phases()->delete();

            foreach (['Proyecto de grado I', 'Proyecto de grado II'] as $index => $defaultName) {
                $period->phases()->create([
                    'name' => $defaultName,
                    'start_date' => $period->start_date,
                    'end_date' => $period->end_date,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }
}
