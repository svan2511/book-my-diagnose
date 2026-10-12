<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contracts\Repositories\BookingRepositoryInterface;
use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBookingStatusRequest;
use App\Http\Resources\BookingDetailResource;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\AuditService;
use App\Services\BookingStatusService;
use App\Services\ReportService;
use App\Services\WhatsApp\WhatsAppShareInterface;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    use ApiResponse;

    /** Patient-facing hint per status, sent with the tracking link. */
    private const STATUS_HINTS = [
        'pending' => 'We have received your booking and will confirm it shortly.',
        'confirmed' => 'Your booking is confirmed. A collector will be assigned shortly.',
        'collection_assigned' => 'A collector has been assigned for your sample pickup.',
        'sample_collected' => 'Your sample has been collected and reached the lab.',
        'testing_in_progress' => 'Your sample is being tested in the lab.',
        'report_ready' => 'Your report is ready. Open the link below to view it.',
        'completed' => 'Your report has been delivered. Thank you for choosing us!',
        'cancelled' => 'This booking has been cancelled. Please contact us for help.',
    ];

    public function index(Request $request, BookingRepositoryInterface $bookings): JsonResponse
    {
        $page = $bookings->adminList($request->only([
            'search', 'status', 'customer_id', 'test_id', 'date_from', 'date_to', 'per_page',
        ]));

        return $this->ok(
            BookingResource::collection($page->items()),
            'Bookings retrieved successfully.',
            [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ]
        );
    }

    public function show(string $booking): JsonResponse
    {
        $model = $this->resolve($booking);

        return $this->ok(
            new BookingDetailResource($model->load(['customer', 'items', 'collection.staff', 'collection.statusHistory', 'reports', 'statusHistory'])),
            'Booking retrieved successfully.'
        );
    }

    public function updateStatus(UpdateBookingStatusRequest $request, string $booking, BookingStatusService $statuses, AuditService $audit): JsonResponse
    {
        $model = $this->resolve($booking);

        $updated = $statuses->transition(
            $model,
            BookingStatus::from($request->validated('status')),
            $request->user()->id,
            $request->validated('notes')
        );

        return $this->ok(new BookingDetailResource($updated), 'Booking status updated successfully.');
    }

    /**
     * Build the patient's live-status WhatsApp message for the CURRENT
     * status. Admin taps "Share Status" after every status change — the
     * patient reopens the same tracking link and stays updated.
     */
    public function shareStatus(string $booking, WhatsAppShareInterface $whatsapp, AuditService $audit, Request $request): JsonResponse
    {
        $model = $this->resolve($booking)->load(['items', 'reports']);

        $status = BookingStatus::tryFrom((string) $model->status) ?? BookingStatus::Pending;
        $frontend = rtrim((string) config('services.frontend.url', 'http://localhost:5173'), '/');
        $trackingUrl = $frontend.'/tracking?booking='.urlencode((string) $model->booking_number)
            .'&phone='.urlencode((string) $model->patient_phone);

        // Download link goes out ONLY after delivery (completed + report
        // exists). Before that the patient gets the tracking link alone —
        // a ready-but-unsent report must never leak.
        $reportUrl = null;
        if ($model->status === BookingStatus::Completed->value && $model->reports->isNotEmpty()) {
            $latest = $model->reports->sortByDesc('created_at')->first();
            $reportUrl = app(ReportService::class)->accessUrl($latest, 24 * 60);
        }

        $share = $whatsapp->statusShare(
            (string) $model->patient_phone,
            (string) $model->patient_name,
            (string) $model->booking_number,
            $model->items->pluck('test_name_snapshot')->filter()->join(', ') ?: 'Diagnostic test',
            $status->label(),
            self::STATUS_HINTS[$model->status] ?? '',
            $trackingUrl,
            $reportUrl
        );

        $audit->log('booking.shared_status', Booking::class, $model->id, [
            'status' => $model->status,
        ], [
            'phone' => $share['phone'],
        ], $request->user()->id);

        return $this->ok($share, "Status update ready to send to {$model->patient_name} on WhatsApp.");
    }

    public function destroy(string $booking, AuditService $audit, Request $request): JsonResponse
    {
        $model = $this->resolve($booking);

        if (! in_array($model->status, ['pending', 'cancelled'], true)) {
            return $this->fail('Only pending or cancelled bookings can be deleted.', [], 422);
        }

        $audit->log('booking.deleted', Booking::class, $model->id, ['booking_number' => $model->booking_number], null, $request->user()->id);
        $model->delete();

        return $this->ok([], 'Booking deleted successfully.');
    }

    private function resolve(string $booking): Booking
    {
        return Booking::where('uuid', $booking)
            ->orWhere('booking_number', $booking)
            ->firstOrFail();
    }
}
