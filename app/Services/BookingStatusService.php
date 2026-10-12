<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\CollectionStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use Illuminate\Support\Facades\DB;

class BookingStatusService
{
    public function __construct(private AuditService $audit) {}

    /**
     * Move a booking to a new status. Only whitelisted transitions allowed;
     * every change is logged and mirrored to the collection job.
     */
    public function transition(Booking $booking, BookingStatus $to, ?int $actorId = null, ?string $notes = null): Booking
    {
        $from = $booking->status();

        if (! in_array($to, BookingStatus::allowedTransitions($from), true)) {
            throw new BusinessRuleException(
                "Cannot move booking from {$from->value} to {$to->value}.",
                ['status' => ["Transition {$from->value} → {$to->value} is not allowed."]]
            );
        }

        if ($to === BookingStatus::ReportReady && $booking->reports()->count() === 0) {
            throw new BusinessRuleException('Upload a report before marking report ready.', [
                'status' => ['No report uploaded for this booking yet.'],
            ]);
        }

        if ($to === BookingStatus::Completed && $booking->reports()->whereNotNull('delivered_at')->count() === 0) {
            throw new BusinessRuleException('Deliver the report before completing the booking.', [
                'status' => ['No delivered report found for this booking yet.'],
            ]);
        }

        return DB::transaction(function () use ($booking, $from, $to, $actorId, $notes) {
            $booking->status = $to->value;
            $booking->save();

            BookingStatusHistory::create([
                'booking_id' => $booking->id,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'changed_by' => $actorId,
                'notes' => $notes,
            ]);

            $this->syncCollection($booking, $to, $actorId);

            $this->audit->log('booking.status_changed', Booking::class, $booking->id,
                ['from' => $from->value], ['to' => $to->value], $actorId);

            return $booking->load(['items', 'customer', 'collection', 'statusHistory', 'reports']);
        });
    }

    private function syncCollection(Booking $booking, BookingStatus $to, ?int $actorId): void
    {
        $collection = $booking->collection;
        if (! $collection) {
            return;
        }

        $map = [
            BookingStatus::CollectionAssigned->value => CollectionStatus::Assigned->value,
            BookingStatus::SampleCollected->value => CollectionStatus::Collected->value,
            BookingStatus::Cancelled->value => CollectionStatus::Cancelled->value,
        ];

        if (! isset($map[$to->value])) {
            return;
        }

        $new = CollectionStatus::from($map[$to->value]);
        $old = CollectionStatus::from($collection->status);

        if (! in_array($new, CollectionStatus::allowedTransitions($old), true)) {
            return; // collection already ahead (e.g. on_the_way) — don't force it back
        }

        $collection->status = $new->value;
        if ($new === CollectionStatus::Collected) {
            $collection->collected_at ??= now();
        }
        $collection->save();

        $collection->statusHistory()->create([
            'from_status' => $old->value,
            'to_status' => $new->value,
            'changed_by' => $actorId,
            'notes' => "Synced from booking {$to->value}.",
        ]);
    }
}
