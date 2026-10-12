<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\CollectionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignCollectionRequest;
use App\Http\Requests\UpdateCollectionStatusRequest;
use App\Http\Resources\CollectionResource;
use App\Models\SampleCollection;
use App\Models\Staff;
use App\Services\AuditService;
use App\Services\CollectionService;
use App\Services\WhatsApp\WhatsAppShareInterface;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $q = SampleCollection::with(['booking.customer', 'booking.items', 'staff'])->latest();

        if ($request->filled('status')) {
            $q->where('status', $request->input('status'));
        }
        if ($request->filled('date')) {
            $q->whereDate('scheduled_at', $request->input('date'));
        }
        if ($request->filled('search')) {
            $s = '%'.str_replace(['%', '_'], '', $request->input('search')).'%';
            $q->where(fn ($w) => $w->where('assigned_to', 'like', $s)
                ->orWhereHas('booking', fn ($b) => $b->where('booking_number', 'like', $s)
                    ->orWhere('patient_name', 'like', $s)));
        }

        $page = $q->paginate(min((int) $request->input('per_page', 20), 100));

        return $this->ok(CollectionResource::collection($page->items()), 'Collections retrieved successfully.', [
            'current_page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function show(string $collection): JsonResponse
    {
        $model = $this->resolve($collection);

        return $this->ok(
            new CollectionResource($model->load(['booking.customer', 'booking.items', 'staff', 'statusHistory'])),
            'Collection retrieved successfully.'
        );
    }

    public function assign(AssignCollectionRequest $request, string $collection, CollectionService $collections, WhatsAppShareInterface $whatsapp): JsonResponse
    {
        $model = $this->resolve($collection);
        $staff = Staff::findOrFail($request->validated('staff_id'));

        if ($request->filled('scheduled_at')) {
            $model->scheduled_at = $request->validated('scheduled_at');
            $model->save();
        }
        if ($request->filled('notes')) {
            $model->notes = $request->validated('notes');
            $model->save();
        }

        $updated = $collections->assign($model, $staff, $request->user()->id);
        $share = $this->sharePayload($updated, $whatsapp);

        return $this->ok([
            'collection' => new CollectionResource($updated),
            'whatsapp' => $share,
        ], "Pickup assigned to {$staff->name}. Send them the location on WhatsApp.");
    }

    public function updateStatus(UpdateCollectionStatusRequest $request, string $collection, CollectionService $collections): JsonResponse
    {
        $model = $this->resolve($collection);

        $updated = $collections->transition(
            $model,
            CollectionStatus::from($request->validated('status')),
            $request->user()->id,
            $request->validated('notes')
        );

        return $this->ok(new CollectionResource($updated), 'Collection status updated successfully.');
    }

    public function shareWhatsApp(string $collection, WhatsAppShareInterface $whatsapp, AuditService $audit, Request $request): JsonResponse
    {
        $model = $this->resolve($collection);
        $staff = $model->staff;
        if (! $staff) {
            return $this->fail('No collector assigned yet. Assign one first, then share the location.', [], 422);
        }

        $booking = $model->booking()->with('items')->first();
        $share = $this->sharePayload($model, $whatsapp);

        $audit->log('collection.shared_whatsapp', SampleCollection::class, $model->id, null, [
            'booking_number' => $booking->booking_number,
            'staff' => $staff->name,
            'phone' => $share['phone'],
        ], $request->user()->id);

        return $this->ok($share, "Pickup location ready to send to {$staff->name} on WhatsApp.");
    }

    private function sharePayload(SampleCollection $collection, WhatsAppShareInterface $whatsapp): array
    {
        $staff = $collection->staff;
        $booking = $collection->booking()->with('items')->first();
        $address = collect([$booking->address, $booking->area, $booking->city, $booking->state, $booking->pincode])
            ->filter()->join(', ');

        return $whatsapp->staffAssignment($staff->phone, [
            'staff_name' => $staff->name,
            'booking_number' => $booking->booking_number,
            'patient_label' => trim("{$booking->patient_name} ({$booking->patient_age}/{$booking->patient_gender})"),
            'tests' => $booking->items->pluck('test_name_snapshot')->join(', ') ?: '—',
            'address' => $address ?: '—',
            'landmark' => $booking->landmark ?: '—',
            // WhatsApp-style map link (same format WhatsApp itself generates for
            // shared locations) — opens in Google Maps, Rapido, Ola, Uber.
            'maps_url' => ($booking->latitude && $booking->longitude)
                ? "https://maps.google.com/?q={$booking->latitude},{$booking->longitude}"
                : null,
            'latitude' => $booking->latitude,
            'longitude' => $booking->longitude,
            'scheduled_at' => $collection->scheduled_at?->format('d M Y, h:i A'),
            'patient_phone' => $booking->patient_phone ? '+91 '.substr($booking->patient_phone, -10) : null,
        ]);
    }

    private function resolve(string $collection): SampleCollection
    {
        return SampleCollection::where('uuid', $collection)->firstOrFail();
    }
}
