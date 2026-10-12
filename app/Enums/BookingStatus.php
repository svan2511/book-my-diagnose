<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case CollectionAssigned = 'collection_assigned';
    case SampleCollected = 'sample_collected';
    case TestingInProgress = 'testing_in_progress';
    case ReportReady = 'report_ready';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Allowed next statuses for a given current status. */
    public static function allowedTransitions(self $from): array
    {
        return match ($from) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::CollectionAssigned, self::Cancelled],
            self::CollectionAssigned => [self::SampleCollected, self::Cancelled],
            self::SampleCollected => [self::TestingInProgress, self::Cancelled],
            self::TestingInProgress => [self::ReportReady, self::Cancelled],
            self::ReportReady => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Booking Received',
            self::Confirmed => 'Booking Confirmed',
            self::CollectionAssigned => 'Collection Assigned',
            self::SampleCollected => 'Sample Collected',
            self::TestingInProgress => 'Testing in Progress',
            self::ReportReady => 'Report Ready',
            self::Completed => 'Report Delivered',
            self::Cancelled => 'Cancelled',
        };
    }
}
