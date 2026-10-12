<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Contracts\Repositories\BookingRepositoryInterface;
use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\TrackBookingRequest;
use App\Http\Resources\BookingDetailResource;
use App\Services\BookingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class BookingController extends Controller
{
    use ApiResponse;

    public function store(StoreBookingRequest $request, BookingService $bookings): JsonResponse
    {
        $booking = $bookings->create($request->validated());

        return $this->created(
            new BookingDetailResource($booking->load(['statusHistory', 'reports'])),
            'Booking created successfully.'
        );
    }

    public function track(TrackBookingRequest $request, BookingRepositoryInterface $bookings): JsonResponse
    {
        $booking = $bookings->findForTracking(
            $request->validated('booking_number'),
            $request->validated('phone')
        );

        if (! $booking) {
            return $this->fail('No booking found for these details.', [], 404);
        }

        // Reports unlock only after the admin actually delivers them
        // (Send on WhatsApp → booking auto-completes). A ready-but-unsent
        // report must never leak to the patient.
        if ($booking->status !== 'completed') {
            $booking->setRelation('reports', collect());
        }

        $timeline = $booking->statusHistory->map(fn ($h) => [
            'status' => $h->to_status,
            'label' => BookingStatus::tryFrom($h->to_status)?->label() ?? $h->to_status,
            'at' => $h->created_at?->toIso8601String(),
            'notes' => $h->notes,
        ]);

        // "Collector on the way" lives on the collection job, not the booking —
        // merge it in so patients see it the moment the admin sets it.
        $onWay = $booking->collection?->statusHistory
            ?->firstWhere('to_status', 'on_the_way');
        if ($onWay) {
            $timeline->push([
                'status' => 'on_the_way',
                'label' => 'Collector On The Way',
                'at' => $onWay->created_at?->toIso8601String(),
                'notes' => $onWay->notes,
            ]);
            $timeline = $timeline->sortBy('at')->values();
        }

        return $this->ok([
            'booking' => new BookingDetailResource($booking),
            'timeline' => $timeline,
        ], 'Booking tracked successfully.');
    }
}
