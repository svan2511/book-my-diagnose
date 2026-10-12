<?php

namespace App\Repositories;

use App\Contracts\Repositories\BookingRepositoryInterface;
use App\Models\Booking;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentBookingRepository implements BookingRepositoryInterface
{
    public function adminList(array $filters): LengthAwarePaginator
    {
        $q = Booking::with(['customer', 'items', 'collection', 'latestReport'])->latest();

        if (! empty($filters['search'])) {
            $s = '%'.str_replace(['%', '_'], '', $filters['search']).'%';
            $q->where(fn ($w) => $w->where('booking_number', 'like', $s)
                ->orWhere('patient_name', 'like', $s)
                ->orWhere('patient_phone', 'like', $s));
        }
        foreach (['status', 'customer_id'] as $f) {
            if (! empty($filters[$f])) {
                $q->where($f, $filters[$f]);
            }
        }
        if (! empty($filters['test_id'])) {
            $q->whereHas('items', fn ($w) => $w->where('test_id', $filters['test_id']));
        }
        if (! empty($filters['date_from'])) {
            $q->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $q->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $q->paginate(min($filters['per_page'] ?? 20, 100));
    }

    /**
     * Tracking requires BOTH booking number and phone — never the
     * number alone (dev-prd §24).
     */
    public function findForTracking(string $bookingNumber, string $phone): ?Booking
    {
        $digits = preg_replace('/\D+/', '', $phone);

        return Booking::with(['items', 'statusHistory', 'reports', 'collection.statusHistory'])
            ->where('booking_number', $bookingNumber)
            ->where(function ($w) use ($phone, $digits) {
                $w->where('patient_phone', $phone)
                    ->orWhere('patient_phone', $digits)
                    ->orWhere('patient_phone', 'like', '%'.substr($digits, -10));
            })
            ->first();
    }
}
