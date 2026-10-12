<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Models\ContactQuery;
use App\Models\LabTest;
use App\Models\Report;
use App\Models\Staff;
use App\Models\User;
use App\Services\BookingService;
use App\Services\BookingStatusService;
use App\Services\CollectionService;
use App\Enums\CollectionStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Realistic demo bookings across every workflow stage.
 * Runs in local environments only — never production data.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $admin = User::where('email', 'admin@bookmydiagnose.in')->firstOrFail();
        $tests = LabTest::active()->get()->keyBy('code');
        if ($tests->isEmpty()) {
            return;
        }

        $people = [
            ['Sunita Sharma', '9820012345', 48, 'female', 'A-402, Sea Breeze Residency', 'Linking Road, Bandra West', 'Mumbai', 'Maharashtra', '400050', 'Near Linking Road Starbucks'],
            ['Ravi Kumar', '9811112222', 35, 'male', 'Flat 5, Green Park', 'Green Park Extension', 'Delhi', 'Delhi', '110016', 'Near Green Park Metro Gate 2'],
            ['Meena Iyer', '9765432109', 52, 'female', '12, Lake View Apartments', 'Ulsoor Lake Road', 'Bengaluru', 'Karnataka', '560001', 'Opp. Lake Park Entrance'],
            ['Amit Patel', '9890123456', 41, 'male', 'B-7, Shivalik Plaza', 'C.G. Road', 'Ahmedabad', 'Gujarat', '380009', 'Next to HDFC Bank ATM'],
            ['Priya Nair', '9745012345', 29, 'female', 'TC 14, Temple Road', 'Sasthamangalam', 'Thiruvananthapuram', 'Kerala', '695010', 'Near Devaswom Board Office'],
            ['Vikram Singh', '9810098100', 55, 'male', 'House 22, Sector 15', 'Sector 15 Market', 'Noida', 'Uttar Pradesh', '201301', 'Behind Sector 15 Police Post'],
            ['Kavita Rao', '9849012345', 63, 'female', '8-2-293, Road No. 12', 'Banjara Hills', 'Hyderabad', 'Telangana', '500034', 'Opp. City Mall Parking'],
            ['Suresh Yadav', '9415012345', 47, 'male', '45, Gomti Nagar', 'Vibhuti Khand', 'Lucknow', 'Uttar Pradesh', '226010', 'Near Fun Mall Crossing'],
            ['Anita Desai', '9822012345', 38, 'female', 'Lane 7, Koregaon Park', 'North Main Road', 'Pune', 'Maharashtra', '411001', 'Beside German Bakery Lane'],
            ['Rahul Verma', '9830012345', 33, 'male', 'Salt Lake, Block BJ', 'Sector 2', 'Kolkata', 'West Bengal', '700091', 'Near City Centre 1 Mall'],
        ];

        $combos = [
            ['CBC001'], ['DIA102'], ['LIP204'], ['THY301'], ['VIT405'],
            ['CBC001', 'DIA102'], ['LFT002', 'KFT003'], ['FBP501'],
            ['CBC001', 'LIP204', 'THY301'], ['VIT405', 'DIA102'],
        ];

        $targets = [
            'confirmed', 'confirmed',
            'collection_assigned', 'collection_assigned',
            'sample_collected', 'sample_collected',
            'testing_in_progress',
            'report_ready', 'report_ready',
            'completed',
        ];

        $bookings = app(BookingService::class);
        $statuses = app(BookingStatusService::class);
        $collections = app(CollectionService::class);

        foreach ($people as $i => $p) {
            [$name, $phone, $age, $gender, $addr, $area, $city, $state, $pin, $landmark] = $p;
            $codes = $combos[$i % count($combos)];
            $ids = collect($codes)->map(fn ($c) => $tests[$c]->id)->all();

            $booking = $bookings->create([
                'test_ids' => $ids,
                'name' => $name, 'phone' => $phone, 'email' => null, 'age' => $age, 'gender' => $gender,
                'patient_name' => $name, 'patient_age' => $age, 'patient_gender' => $gender,
                'patient_phone' => $phone, 'patient_email' => null,
                'address' => $addr, 'area' => $area, 'city' => $city, 'state' => $state,
                'pincode' => $pin, 'landmark' => $landmark,
                'latitude' => 19.0760 + ($i * 0.002), 'longitude' => 72.8777 + ($i * 0.002),
            ]);
            // Spread creation dates over the last 10 days for analytics.
            $booking->created_at = now()->subDays(10 - $i)->setTime(9 + ($i % 8), 15);
            $booking->updated_at = $booking->created_at;
            $booking->save();

            $target = $targets[$i];
            $flow = ['confirmed', 'collection_assigned', 'sample_collected', 'testing_in_progress', 'report_ready', 'completed'];

            foreach ($flow as $step) {
                if ($step === 'collection_assigned') {
                    $picker = Staff::firstOrCreate(
                        ['phone' => $i % 2 ? '9810012345' : '9890098900'],
                        ['name' => $i % 2 ? 'Amit Verma' : 'Rajesh Sharma', 'area' => 'Delhi NCR']
                    );
                    $collections->assign($booking->collection()->firstOrFail(), $picker, $admin->id);
                }
                if ($step === 'report_ready') {
                    $this->attachDummyReport($booking->id, $admin->id);
                }
                if ($step === 'completed') {
                    Report::where('booking_id', $booking->id)->update([
                        'status' => 'delivered',
                        'delivered_at' => now(),
                    ]);
                }
                $booking = $statuses->transition($booking->fresh(), BookingStatus::from($step), $admin->id);
                if ($step === $target) {
                    break;
                }
                if ($target === 'cancelled') {
                    break;
                }
            }
        }

        // One cancelled booking for status coverage.
        $cancelled = $bookings->create([
            'test_ids' => [$tests['CBC001']->id],
            'name' => 'Neha Gupta', 'phone' => '9818012345', 'email' => null, 'age' => 26, 'gender' => 'female',
            'patient_name' => 'Neha Gupta', 'patient_age' => 26, 'patient_gender' => 'female',
            'patient_phone' => '9818012345', 'patient_email' => null,
            'address' => 'Plot 9, Vaishali', 'area' => 'Sector 4', 'city' => 'Ghaziabad', 'state' => 'Uttar Pradesh',
            'pincode' => '201010', 'landmark' => 'Near Vaishali Metro',
            'latitude' => 28.6692, 'longitude' => 77.4538,
        ]);
        $statuses->transition($cancelled->fresh(), BookingStatus::Cancelled, $admin->id, 'Duplicate booking, patient requested cancellation.');

        ContactQuery::factory()->count(4)->create();
    }

    private function attachDummyReport(int $bookingId, int $adminId): void
    {
        $path = "booking-{$bookingId}/demo-report-".now()->format('YmdHis').'.pdf';
        Storage::disk('reports')->put($path, "%PDF-1.4\n%Demo report for booking {$bookingId}\n");

        Report::create([
            'booking_id' => $bookingId,
            'disk' => 'reports',
            'file_path' => $path,
            'original_name' => 'demo-report.pdf',
            'mime' => 'application/pdf',
            'file_size' => Storage::disk('reports')->size($path),
            'uploaded_by' => $adminId,
            'status' => 'ready',
        ]);
    }
}
