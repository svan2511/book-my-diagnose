<?php

namespace Database\Factories;

use App\Models\ContactQuery;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactQueryFactory extends Factory
{
    protected $model = ContactQuery::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'phone' => '9'.$this->faker->numerify('#########'),
            'email' => $this->faker->safeEmail(),
            'subject' => $this->faker->sentence(4),
            'message' => $this->faker->paragraph(),
            'status' => 'new',
        ];
    }
}
