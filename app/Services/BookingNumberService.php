<?php

namespace App\Services;

use App\Models\Booking;

/**
 * Human-readable booking numbers: BK-YYYYMMDD-<id padded to 6>.
 * Derived from the auto-increment id inside the creation transaction,
 * so it is concurrency-safe (unique constraint backs it up).
 */
class BookingNumberService
{
    public function for(Booking $booking): string
    {
        return sprintf('BK-%s-%06d', $booking->created_at->format('Ymd'), $booking->id);
    }
}
