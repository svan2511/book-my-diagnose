<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\Customer;
use App\Models\LabTest;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        private BookingNumberService $numbers,
        private AuditService $audit,
    ) {}

    /**
     * Create a booking from validated public input. Prices are ALWAYS
     * computed server-side — frontend totals are never trusted.
     */
    public function create(array $input): Booking
    {
        return DB::transaction(function () use ($input) {
            $tests = LabTest::active()
                ->whereIn('id', $input['test_ids'])
                ->lockForUpdate()
                ->get();

            if ($tests->count() !== count(array_unique($input['test_ids']))) {
                throw new BusinessRuleException('One or more selected tests are unavailable.');
            }

            if ($tests->contains(fn (LabTest $t) => ! $t->home_collection_available)) {
                throw new BusinessRuleException('One or more selected tests do not support home collection.');
            }

            // Customer upsert by verified phone — no duplicates per booking.
            $customer = Customer::where('phone', $input['phone'])->first();
            if ($customer) {
                $customer->fill([
                    'name' => $input['name'],
                    'email' => $input['email'] ?? $customer->email,
                    'gender' => $input['gender'] ?? $customer->gender,
                    'age' => $input['age'] ?? $customer->age,
                ]);
                if ($customer->isDirty()) {
                    $customer->save();
                }
            } else {
                $customer = Customer::create([
                    'name' => $input['name'],
                    'phone' => $input['phone'],
                    'email' => $input['email'] ?? null,
                    'gender' => $input['gender'] ?? null,
                    'age' => $input['age'] ?? null,
                ]);
            }

            $subtotal = $tests->sum('price');

            /** @var Booking $booking */
            $booking = Booking::create([
                'customer_id' => $customer->id,
                'booking_number' => 'PENDING',
                'status' => BookingStatus::Pending->value,
                'subtotal' => $subtotal,
                'discount' => 0,
                'total' => $subtotal,
                'currency' => 'INR',
                'patient_name' => $input['patient_name'],
                'patient_age' => $input['patient_age'],
                'patient_gender' => $input['patient_gender'],
                'patient_phone' => $input['patient_phone'],
                'patient_email' => $input['patient_email'] ?? null,
                'address' => $input['address'],
                'area' => $input['area'] ?? null,
                'city' => $input['city'],
                'state' => $input['state'],
                'pincode' => $input['pincode'],
                'landmark' => $input['landmark'],
                'latitude' => $input['latitude'] ?? null,
                'longitude' => $input['longitude'] ?? null,
                'location_accuracy' => $input['location_accuracy'] ?? null,
                'scheduled_at' => $input['scheduled_at'] ?? null,
            ]);

            foreach ($tests as $test) {
                $booking->items()->create([
                    'test_id' => $test->id,
                    'test_name_snapshot' => $test->name,
                    'test_code_snapshot' => $test->code,
                    'price_snapshot' => $test->price,
                ]);
            }

            $booking->booking_number = $this->numbers->for($booking);
            $booking->save();

            BookingStatusHistory::create([
                'booking_id' => $booking->id,
                'from_status' => null,
                'to_status' => BookingStatus::Pending->value,
            ]);

            $booking->collection()->create(['status' => 'pending']);

            $this->audit->log('booking.created', Booking::class, $booking->id, null, [
                'booking_number' => $booking->booking_number,
                'total' => (float) $booking->total,
            ]);

            return $booking->load(['items', 'customer', 'collection']);
        });
    }
}
