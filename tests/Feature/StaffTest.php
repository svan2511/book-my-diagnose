<?php

namespace Tests\Feature;

use App\Models\LabTest;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffTest extends TestCase
{
    use RefreshDatabase;

    private function headers(): array
    {
        $token = User::factory()->create()->createToken('t')->plainTextToken;

        return ['Authorization' => "Bearer $token"];
    }

    private function createBooking(array $overrides = []): string
    {
        $testId = LabTest::factory()->create(['price' => 499])->id;
        $res = $this->postJson('/api/v1/bookings', array_merge([
            'test_ids' => [$testId],
            'name' => 'Ravi Kumar',
            'phone' => '9811112222',
            'patient_name' => 'Ravi Kumar',
            'patient_age' => 35,
            'patient_gender' => 'male',
            'patient_phone' => '9811112222',
            'address' => 'Flat 5, Green Park',
            'city' => 'Delhi',
            'state' => 'Delhi',
            'pincode' => '110016',
            'landmark' => 'Near Green Park Metro',
            'latitude' => 28.5535,
            'longitude' => 77.2588,
        ], $overrides));
        $res->assertCreated();

        return $res->json('data.booking_number');
    }

    public function test_admin_guests_cannot_list_staff(): void
    {
        $this->getJson('/api/v1/admin/staff')->assertStatus(401);
    }

    public function test_create_staff_validates_phone(): void
    {
        $h = $this->headers();

        $this->postJson('/api/v1/admin/staff', ['name' => 'No Phone'], $h)->assertStatus(422);

        $this->postJson('/api/v1/admin/staff', ['name' => 'Bad', 'phone' => '12345'], $h)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);

        $ok = $this->postJson('/api/v1/admin/staff', [
            'name' => 'Rajesh Sharma', 'phone' => '+91 98100-12345', 'area' => 'South Delhi',
        ], $h)->assertCreated()->assertJsonPath('success', true);

        // Normalized to canonical 10 digits.
        $this->assertEquals('9810012345', $ok->json('data.phone'));
        $this->assertDatabaseHas('staff', ['phone' => '9810012345', 'deleted_at' => null]);
    }

    public function test_duplicate_phone_returns_friendly_error(): void
    {
        $h = $this->headers();
        Staff::factory()->create(['phone' => '9810012345']);

        $res = $this->postJson('/api/v1/admin/staff', ['name' => 'Copy', 'phone' => '9810012345'], $h);

        $res->assertStatus(422)->assertJsonPath('success', false);
        $body = $res->getContent();
        $this->assertStringNotContainsStringIgnoringCase('SQLSTATE', $body);
    }

    public function test_recreating_deleted_staff_restores_it(): void
    {
        $h = $this->headers();
        $staff = Staff::factory()->create(['phone' => '9810012345']);
        $staff->delete();

        $this->postJson('/api/v1/admin/staff', ['name' => 'Rajesh Sharma', 'phone' => '9810012345'], $h)
            ->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('staff', ['id' => $staff->id, 'deleted_at' => null]);
        $this->assertEquals(1, Staff::withTrashed()->where('phone', '9810012345')->count());
    }

    public function test_search_finds_by_name_or_phone(): void
    {
        $h = $this->headers();
        Staff::factory()->create(['name' => 'Rajesh Sharma', 'phone' => '9810012345']);
        Staff::factory()->create(['name' => 'Amit Verma', 'phone' => '9890098900']);

        $this->getJson('/api/v1/admin/staff?search=rajesh', $h)
            ->assertOk()->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/admin/staff?search=98900', $h)
            ->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Amit Verma');
    }

    public function test_update_and_status_toggle(): void
    {
        $h = $this->headers();
        $staff = Staff::factory()->create(['phone' => '9810012345']);

        $this->patchJson("/api/v1/admin/staff/{$staff->uuid}", ['area' => 'Green Park'], $h)
            ->assertOk()->assertJsonPath('data.area', 'Green Park');

        $this->patchJson("/api/v1/admin/staff/{$staff->uuid}", ['status' => 'inactive'], $h)
            ->assertOk()->assertJsonPath('data.status', 'inactive');
    }

    public function test_delete_blocked_with_active_pickup(): void
    {
        $h = $this->headers();
        $number = $this->createBooking();
        $this->patchJson("/api/v1/admin/bookings/{$number}/status", ['status' => 'confirmed'], $h)->assertOk();

        $staff = Staff::factory()->create(['phone' => '9810012345']);
        $collectionUuid = $this->getJson("/api/v1/admin/bookings/{$number}", $h)->json('data.collection.id');

        $this->postJson("/api/v1/admin/collections/{$collectionUuid}/assign", ['staff_id' => $staff->uuid], $h)->assertOk();

        $this->deleteJson("/api/v1/admin/staff/{$staff->uuid}", [], $h)
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        // After the job is collected, deletion is allowed (snapshot retained).
        $this->patchJson("/api/v1/admin/collections/{$collectionUuid}/status", ['status' => 'on_the_way'], $h)->assertOk();
        $this->patchJson("/api/v1/admin/collections/{$collectionUuid}/status", ['status' => 'collected'], $h)->assertOk();
        $this->deleteJson("/api/v1/admin/staff/{$staff->uuid}", [], $h)->assertOk();
        $this->assertSoftDeleted('staff', ['id' => $staff->id]);
    }

    public function test_assign_returns_whatsapp_with_location(): void
    {
        $h = $this->headers();
        $number = $this->createBooking();
        $this->patchJson("/api/v1/admin/bookings/{$number}/status", ['status' => 'confirmed'], $h)->assertOk();

        $staff = Staff::factory()->create(['name' => 'Rajesh Sharma', 'phone' => '9810012345']);
        $collectionUuid = $this->getJson("/api/v1/admin/bookings/{$number}", $h)->json('data.collection.id');

        $res = $this->postJson("/api/v1/admin/collections/{$collectionUuid}/assign", ['staff_id' => $staff->uuid], $h);

        $res->assertOk()
            ->assertJsonPath('data.collection.assigned_to', 'Rajesh Sharma')
            ->assertJsonPath('data.collection.staff.name', 'Rajesh Sharma')
            ->assertJsonStructure(['data' => ['whatsapp' => ['phone', 'message', 'url']]]);

        $wa = $res->json('data.whatsapp');
        $this->assertStringStartsWith('https://wa.me/919810012345', $wa['url']);
        // Location, landmark and map link all travel in the message.
        $this->assertStringContainsString('Near Green Park Metro', $wa['message']);
        $this->assertStringContainsString('Flat 5, Green Park', $wa['message']);
        $this->assertStringContainsString('maps.google.com/?q=', $wa['message']);
        $this->assertStringContainsString($number, $wa['message']);
    }

    public function test_assign_rejects_unknown_staff_and_inactive_staff(): void
    {
        $h = $this->headers();
        $number = $this->createBooking();
        $this->patchJson("/api/v1/admin/bookings/{$number}/status", ['status' => 'confirmed'], $h)->assertOk();
        $collectionUuid = $this->getJson("/api/v1/admin/bookings/{$number}", $h)->json('data.collection.id');

        $this->postJson("/api/v1/admin/collections/{$collectionUuid}/assign", ['staff_id' => 999999], $h)
            ->assertStatus(422);

        $off = Staff::factory()->create(['status' => 'inactive']);
        $this->postJson("/api/v1/admin/collections/{$collectionUuid}/assign", ['staff_id' => $off->uuid], $h)
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_share_endpoint_regenerates_link_and_rejects_unassigned(): void
    {
        $h = $this->headers();
        $number = $this->createBooking();
        $this->patchJson("/api/v1/admin/bookings/{$number}/status", ['status' => 'confirmed'], $h)->assertOk();
        $collectionUuid = $this->getJson("/api/v1/admin/bookings/{$number}", $h)->json('data.collection.id');

        // No collector yet — friendly 422, no crash.
        $this->postJson("/api/v1/admin/collections/{$collectionUuid}/share-whatsapp", [], $h)
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $staff = Staff::factory()->create(['name' => 'Rajesh Sharma', 'phone' => '9810012345']);
        $this->postJson("/api/v1/admin/collections/{$collectionUuid}/assign", ['staff_id' => $staff->uuid], $h)->assertOk();

        $res = $this->postJson("/api/v1/admin/collections/{$collectionUuid}/share-whatsapp", [], $h);
        $res->assertOk()->assertJsonStructure(['data' => ['phone', 'message', 'url']]);
        $this->assertStringStartsWith('https://wa.me/919810012345', $res->json('data.url'));
        $this->assertStringContainsString('Near Green Park Metro', $res->json('data.message'));
    }
}
