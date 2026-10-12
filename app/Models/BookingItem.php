<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingItem extends Model
{
    protected $fillable = [
        'booking_id', 'test_id', 'test_name_snapshot', 'test_code_snapshot', 'price_snapshot',
    ];

    protected function casts(): array
    {
        return ['price_snapshot' => 'decimal:2'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(LabTest::class, 'test_id');
    }
}
