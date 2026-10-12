<?php

namespace App\Contracts\Repositories;

use App\Models\Booking;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BookingRepositoryInterface
{
    public function adminList(array $filters): LengthAwarePaginator;

    public function findForTracking(string $bookingNumber, string $phone): ?Booking;
}
