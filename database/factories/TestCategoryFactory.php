<?php

namespace Database\Factories;

use App\Models\TestCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TestCategoryFactory extends Factory
{
    protected $model = TestCategory::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name).'-'.$this->faker->unique()->randomNumber(4),
            'description' => $this->faker->sentence(),
            'status' => 'active',
        ];
    }
}
