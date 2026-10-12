<?php

namespace Database\Factories;

use App\Models\LabTest;
use App\Models\TestCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class LabTestFactory extends Factory
{
    protected $model = LabTest::class;

    public function definition(): array
    {
        $price = $this->faker->numberBetween(299, 1999);

        return [
            'category_id' => TestCategory::factory(),
            'name' => ucwords($this->faker->words(3, true)),
            'code' => strtoupper($this->faker->unique()->lexify('???').$this->faker->unique()->randomNumber(3)),
            'description' => $this->faker->sentence(12),
            'preparation_instructions' => 'Overnight fasting may be required. Plain water is permitted.',
            'sample_type' => 'Blood',
            'price' => $price,
            'mrp' => $price + $this->faker->numberBetween(100, 500),
            'turnaround_hours' => $this->faker->randomElement([8, 12, 24, 48]),
            'parameter_count' => $this->faker->numberBetween(1, 84),
            'home_collection_available' => true,
            'status' => 'active',
        ];
    }
}
