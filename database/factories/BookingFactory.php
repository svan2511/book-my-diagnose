<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            'booking_number' => 'BK-'.$this->faker->date('Ymd').'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'customer_id' => Customer::factory(),
            'status' => 'pending',
            'subtotal' => 499,
            'discount' => 0,
            'total' => 499,
            'currency' => 'INR',
            'patient_name' => $this->faker->name(),
            'patient_age' => $this->faker->numberBetween(18, 80),
            'patient_gender' => $this->faker->randomElement(['male', 'female']),
            'patient_phone' => $this->faker->numerify('##########'),
            'patient_email' => $this->faker->optional()->safeEmail(),
            'address' => $this->faker->streetAddress(),
            'area' => $this->faker->streetName(),
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => $this->faker->numerify('######'),
            'landmark' => 'Near '.$this->faker->company(),
        ];
    }
}
