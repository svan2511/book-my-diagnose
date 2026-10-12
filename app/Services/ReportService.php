<?php

namespace App\Services;

use App\Contracts\MediaStorageInterface;
use App\Enums\BookingStatus;
use App\Enums\ReportStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\Report;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function __construct(
        private MediaStorageInterface $storage,
        private AuditService $audit,
    ) {}

    public function upload(Booking $booking, UploadedFile $file, int $uploadedBy): Report
    {
        if (in_array($booking->status, [BookingStatus::Cancelled->value, BookingStatus::Completed->value], true)) {
            throw new BusinessRuleException('Cannot upload a report for a cancelled or completed booking.');
        }
        // A report can only exist after lab testing starts. Uploading earlier
        // (pending/confirmed/collection) would attach a report to a booking
        // whose sample was never tested. Re-upload on report_ready stays
        // allowed so a wrong file can be corrected.
        if (! in_array($booking->status, [BookingStatus::TestingInProgress->value, BookingStatus::ReportReady->value], true)) {
            throw new BusinessRuleException(
                'Reports can only be uploaded after testing starts (current status: '.str_replace('_', ' ', (string) $booking->status).').'
            );
        }

        return DB::transaction(function () use ($booking, $file, $uploadedBy) {
            $path = $this->storage->store($file, 'booking-'.$booking->id);

            $report = Report::create([
                'booking_id' => $booking->id,
                'disk' => 'reports',
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType() ?? 'application/octet-stream',
                'file_size' => $file->getSize() ?? 0,
                'uploaded_by' => $uploadedBy,
                'status' => ReportStatus::Ready->value,
            ]);

            $this->audit->log('report.uploaded', Report::class, $report->id, null, [
                'booking_number' => $booking->booking_number,
                'file' => $report->original_name,
            ], $uploadedBy);

            return $report;
        });
    }

    public function accessUrl(Report $report, int $ttlMinutes = 60): string
    {
        return $this->storage->temporaryUrl($report->file_path, $ttlMinutes);
    }
}
