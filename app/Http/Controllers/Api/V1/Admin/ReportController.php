<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\BookingStatus;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\Report;
use App\Services\AuditService;
use App\Services\ReportService;
use App\Services\WhatsApp\WhatsAppShareInterface;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $q = Report::with('booking')->latest();

        if ($request->filled('status')) {
            $q->where('status', $request->input('status'));
        }
        if ($request->filled('search')) {
            $s = '%'.str_replace(['%', '_'], '', $request->input('search')).'%';
            $q->where(fn ($w) => $w->where('original_name', 'like', $s)
                ->orWhereHas('booking', fn ($b) => $b->where('booking_number', 'like', $s)));
        }

        $page = $q->paginate(min((int) $request->input('per_page', 20), 100));

        return $this->ok(ReportResource::collection($page->items()), 'Reports retrieved successfully.', [
            'current_page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function upload(UploadReportRequest $request, string $booking, ReportService $reports): JsonResponse
    {
        $model = Booking::where('uuid', $booking)->orWhere('booking_number', $booking)->firstOrFail();

        $report = $reports->upload($model, $request->file('file'), $request->user()->id);

        return $this->created(new ReportResource($report->load('booking')), 'Report uploaded successfully.');
    }

    /**
     * Stream a report file. Route is signed — no permanent public URL exists.
     */
    public function file(Request $request)
    {
        $path = (string) $request->query('path');

        $report = Report::where('file_path', $path)->firstOrFail();

        abort_unless(Storage::disk('reports')->exists($path), 404);

        app(AuditService::class)->log('report.accessed', Report::class, $report->id, null, null, $request->user()?->id);

        return Storage::disk('reports')->download($path, $report->original_name, [
            'Content-Type' => $report->mime,
        ]);
    }

    public function shareWhatsApp(string $report, WhatsAppShareInterface $whatsapp, ReportService $reports, AuditService $audit, Request $request): JsonResponse
    {
        $model = Report::with('booking.items')->where('uuid', $report)->firstOrFail();
        $booking = $model->booking;

        $share = $whatsapp->reportShare(
            $booking->patient_phone,
            $booking->patient_name,
            $booking->booking_number,
            $booking->items->pluck('test_name_snapshot')->join(', '),
            $reports->accessUrl($model, 24 * 60)
        );

        $model->status = ReportStatus::Delivered->value;
        $model->delivered_at ??= now();
        $model->save();

        if ($booking->status === BookingStatus::ReportReady->value) {
            $booking->status = BookingStatus::Completed->value;
            $booking->save();
            BookingStatusHistory::create([
                'booking_id' => $booking->id,
                'from_status' => BookingStatus::ReportReady->value,
                'to_status' => BookingStatus::Completed->value,
                'changed_by' => $request->user()->id,
                'notes' => 'Report shared via WhatsApp.',
            ]);
        }

        $audit->log('report.shared_whatsapp', Report::class, $model->id, null, [
            'booking_number' => $booking->booking_number,
            'phone' => $share['phone'],
        ], $request->user()->id);

        return $this->ok($share, 'WhatsApp share link prepared successfully.');
    }
}
