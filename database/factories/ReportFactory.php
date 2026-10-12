<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'disk' => 'reports',
            'file_path' => 'booking-1/'.$this->faker->uuid().'.pdf',
            'original_name' => 'report.pdf',
            'mime' => 'application/pdf',
            'file_size' => 102400,
            'uploaded_by' => User::factory(),
            'status' => 'ready',
        ];
    }
}
