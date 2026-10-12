<?php

namespace App\Services;

use App\Enums\CollectionStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\SampleCollection;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

class CollectionService
{
    public function __construct(private AuditService $audit) {}

    public function assign(SampleCollection $collection, Staff $staff, ?int $actorId = null): SampleCollection
    {
        if ($collection->booking->status === 'cancelled') {
            throw new BusinessRuleException('Cannot assign a cancelled booking.');
        }
        if ($staff->status !== 'active') {
            throw new BusinessRuleException('Cannot assign an inactive staff member. Activate them first.');
        }

        return DB::transaction(function () use ($collection, $staff, $actorId) {
            $collection->staff_id = $staff->id;
            $collection->assigned_to = $staff->name;
            if ($collection->status === CollectionStatus::Pending->value) {
                $this->record($collection, CollectionStatus::Assigned, $actorId, "Assigned to {$staff->name}.");
                $collection->status = CollectionStatus::Assigned->value;
            }
            $collection->save();

            $this->audit->log('collection.assigned', SampleCollection::class, $collection->id,
                null, ['staff_id' => $staff->id, 'assigned_to' => $staff->name], $actorId);

            return $collection->load(['booking', 'staff', 'statusHistory']);
        });
    }

    public function transition(SampleCollection $collection, CollectionStatus $to, ?int $actorId = null, ?string $notes = null): SampleCollection
    {
        $from = CollectionStatus::from($collection->status);

        if (! in_array($to, CollectionStatus::allowedTransitions($from), true)) {
            throw new BusinessRuleException("Cannot move collection from {$from->value} to {$to->value}.");
        }

        return DB::transaction(function () use ($collection, $from, $to, $actorId, $notes) {
            $collection->status = $to->value;
            if ($to === CollectionStatus::OnTheWay) {
                $collection->started_at ??= now();
            }
            if ($to === CollectionStatus::Collected) {
                $collection->collected_at ??= now();
            }
            $collection->save();

            $this->record($collection, $to, $actorId, $notes);

            $this->audit->log('collection.status_changed', SampleCollection::class, $collection->id,
                ['from' => $from->value], ['to' => $to->value], $actorId);

            return $collection->load(['booking', 'statusHistory']);
        });
    }

    private function record(SampleCollection $collection, CollectionStatus $to, ?int $actorId, ?string $notes): void
    {
        $collection->statusHistory()->create([
            'from_status' => $collection->getOriginal('status') ?? $collection->status,
            'to_status' => $to->value,
            'changed_by' => $actorId,
            'notes' => $notes,
        ]);
    }
}
