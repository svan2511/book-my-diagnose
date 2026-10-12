<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LabTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBookingTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        $test = LabTest::factory()->create(['price' => 499, 'home_collection_available' => true]);

        return array_merge([
            'test_ids' => [$test->id],
            'name' => 'Sunita Sharma',
            'phone' => '9820012345',
            'email' => 'sunita@example.com',
            'patient_name' => 'Sunita Sharma',
            'patient_age' => 48,
            'patient_gender' => 'female',
            'patient_phone' => '+91-9820012345',
            'address' => 'A-402, Sea Breeze Residency',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400050',
            'landmark' => 'Near Linking Road Starbucks',
            'latitude' => 19.0760,
            'longitude' => 72.8777,
        ], $overrides);
    }

    public function test_booking_is_created_with_server_side_pricing(): void
    {
        $res = $this->postJson('/api/v1/bookings', $this->payload());

        $res->assertCreated()->assertJsonPath('success', true);
        $number = $res->json('data.booking_number');
        $this->assertMatchesRegularExpression('/^BK-\d{8}-\d{6}$/', $number);
        $this->assertEquals(499.0, $res->json('data.total'));
        // phone normalized to 10 digits
        $this->assertEquals('9820012345', $res->json('data.patient.phone'));
    }

    public function test_duplicate_customer_is_not_created_for_same_phone(): void
    {
        $this->postJson('/api/v1/bookings', $this->payload())->assertCreated();
        $this->postJson('/api/v1/bookings', $this->payload())->assertCreated();

        $this->assertEquals(1, Customer::where('phone', '9820012345')->count());
    }

    public function test_booking_rejects_unknown_or_inactive_test(): void
    {
        $this->postJson('/api/v1/bookings', $this->payload(['test_ids' => [99999]]))
            ->assertStatus(422);

        $inactive = LabTest::factory()->create(['status' => 'inactive']);
        $this->postJson('/api/v1/bookings', $this->payload(['test_ids' => [$inactive->id]]))
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_booking_rejects_invalid_phone_and_pincode(): void
    {
        $this->postJson('/api/v1/bookings', $this->payload(['phone' => '12345', 'patient_phone' => '12345']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);

        $this->postJson('/api/v1/bookings', $this->payload(['pincode' => '4000']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pincode']);
    }

    public function test_booking_rejects_missing_gps_location(): void
    {
        $payload = $this->payload();
        unset($payload['latitude'], $payload['longitude']);

        $this->postJson('/api/v1/bookings', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_test_catalog_search_and_filters(): void
    {
        LabTest::factory()->create(['name' => 'Complete Blood Count', 'code' => 'CBC001']);
        LabTest::factory()->create(['name' => 'Lipid Profile', 'code' => 'LIP204', 'price' => 799]);

        $this->getJson('/api/v1/tests?search=cbc')->assertOk()
            ->assertJsonFragment(['code' => 'CBC001']);

        $this->getJson('/api/v1/tests?max_price=500')->assertOk()
            ->assertJsonMissing(['code' => 'LIP204']);

        $this->getJson('/api/v1/test-categories')->assertOk()
            ->assertJsonPath('success', true);
    }
}
