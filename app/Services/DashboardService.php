<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\LabTest;
use App\Models\SampleCollection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function summary(): array
    {
        $today = now()->startOfDay();

        $revenue = (float) Booking::whereNotIn('status', [BookingStatus::Cancelled->value])->sum('total');

        return [
            'total_bookings' => Booking::count(),
            'today_bookings' => Booking::where('created_at', '>=', $today)->count(),
            'total_customers' => Customer::count(),
            'total_tests' => LabTest::active()->count(),
            'samples_collected' => Booking::whereIn('status', [
                BookingStatus::SampleCollected->value,
                BookingStatus::TestingInProgress->value,
                BookingStatus::ReportReady->value,
                BookingStatus::Completed->value,
            ])->count(),
            'pending_collections' => SampleCollection::whereIn('status', ['pending', 'assigned', 'on_the_way'])->count(),
            'reports_ready' => Booking::where('status', BookingStatus::ReportReady->value)->count(),
            'completed_bookings' => Booking::where('status', BookingStatus::Completed->value)->count(),
            'cancelled_bookings' => Booking::where('status', BookingStatus::Cancelled->value)->count(),
            'total_revenue' => $revenue,
        ];
    }

    /**
     * Time-bucketed analytics. Single aggregated queries per series —
     * no per-day loops (dev-prd §81).
     */
    public function analytics(string $period, ?string $from = null, ?string $to = null): array
    {
        $toDate = $to ? now()->parse($to)->endOfDay() : now()->endOfDay();
        $fromDate = $from
            ? now()->parse($from)->startOfDay()
            : match ($period) {
                'daily' => $toDate->copy()->subDays(13)->startOfDay(),
                'weekly' => $toDate->copy()->subWeeks(11)->startOfDay(),
                'monthly' => $toDate->copy()->subMonths(11)->startOfDay(),
                default => $toDate->copy()->subYears(4)->startOfDay(),
            };

        $driver = DB::connection()->getDriverName();
        // Weekly buckets are keyed by the week's Monday (Y-m-d) on every
        // driver, so labels/zero-fill stay readable and identical.
        $bucket = match ($period) {
            'weekly' => $driver === 'sqlite'
                ? "date(created_at, 'weekday 0', '-6 days')"
                : 'DATE_SUB(DATE(created_at), INTERVAL WEEKDAY(created_at) DAY)',
            'monthly' => $driver === 'sqlite' ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')",
            'yearly' => $driver === 'sqlite' ? "strftime('%Y', created_at)" : "DATE_FORMAT(created_at, '%Y')",
            default => $driver === 'sqlite' ? 'date(created_at)' : 'DATE(created_at)',
        };

        $series = Booking::whereBetween('created_at', [$fromDate, $toDate])
            ->whereNotIn('status', [BookingStatus::Cancelled->value])
            ->selectRaw("{$bucket} as bucket, COUNT(*) as bookings, COALESCE(SUM(total), 0) as revenue")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get()
            ->keyBy('bucket');

        // Zero-fill: the chart must show every bucket in range (14 days for
        // daily etc.), not just days that have bookings.
        $series = $this->zeroFillSeries($series, $period, $fromDate, $toDate);

        $byStatus = Booking::whereBetween('created_at', [$fromDate, $toDate])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $popular = DB::table('booking_items')
            ->join('bookings', 'bookings.id', '=', 'booking_items.booking_id')
            ->whereBetween('bookings.created_at', [$fromDate, $toDate])
            ->whereNotIn('bookings.status', [BookingStatus::Cancelled->value])
            ->selectRaw('test_name_snapshot as test, test_code_snapshot as code, COUNT(*) as bookings, COALESCE(SUM(price_snapshot), 0) as revenue')
            ->groupBy('test_name_snapshot', 'test_code_snapshot')
            ->orderByDesc('bookings')
            ->limit(8)
            ->get();

        return [
            'period' => $period,
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'series' => $series,
            'by_status' => $byStatus,
            'popular_tests' => $popular,
            'new_customers' => Customer::whereBetween('created_at', [$fromDate, $toDate])->count(),
        ];
    }

    /**
     * Fill every expected bucket key in range with zeros where the DB
     * returned no rows. Keys mirror the SQL bucket expressions exactly
     * so the merge aligns.
     */
    private function zeroFillSeries($rows, string $period, $from, $to): array
    {
        $filled = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        $step = match ($period) {
            'daily' => 'day',
            'weekly' => 'week',
            'monthly' => 'month',
            default => 'year',
        };

        // Align weekly cursor to the week's Monday so every step hits a new bucket.
        if ($step === 'week' && (int) $cursor->format('N') !== 1) {
            $cursor->modify('last monday');
        }
        if ($step === 'month') {
            $cursor->startOfMonth();
        }
        if ($step === 'year') {
            $cursor->startOfYear();
        }

        while ($cursor <= $end) {
            $key = match ($step) {
                'day', 'week' => $cursor->format('Y-m-d'),
                'month' => $cursor->format('Y-m'),
                default => $cursor->format('Y'),
            };

            if (! isset($filled[$key])) {
                $row = $rows->get($key);
                $filled[$key] = [
                    'bucket' => $key,
                    'bookings' => $row ? (int) $row->bookings : 0,
                    'revenue' => $row ? (float) $row->revenue : 0,
                ];
            }

            $cursor->modify('+1 '.$step);
        }

        return array_values($filled);
    }
}
