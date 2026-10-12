<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\SampleCollection;
use Illuminate\Database\Eloquent\Factories\Factory;

class SampleCollectionFactory extends Factory
{
    protected $model = SampleCollection::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'status' => 'pending',
        ];
    }
}
