<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\LabTest;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class TrackingAndReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracking_needs_booking_number_and_phone(): void
    {
        $test = LabTest::factory()->create(['price' => 399]);
        $created = $this->postJson('/api/v1/bookings', [
            'test_ids' => [$test->id],
            'name' => 'Meena Iyer',
            'phone' => '9765432109',
            'patient_name' => 'Meena Iyer',
            'patient_age' => 52,
            'patient_gender' => 'female',
            'patient_phone' => '9765432109',
            'address' => '12, Lake View',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560001',
            'landmark' => 'Near Lake Park Gate',
            'latitude' => 12.9784,
            'longitude' => 77.6401,
        ])->assertCreated();
        $number = $created->json('data.booking_number');

        // correct pair works
        $this->getJson("/api/v1/bookings/track?booking_number={$number}&phone=9765432109")
            ->assertOk()
            ->assertJsonPath('data.booking.booking_number', $number)
            ->assertJsonStructure(['data' => ['booking', 'timeline']]);

        // wrong phone -> not found (never leak existence)
        $this->getJson("/api/v1/bookings/track?booking_number={$number}&phone=9000000001")
            ->assertStatus(404);

        // missing phone -> validation error
        $this->getJson("/api/v1/bookings/track?booking_number={$number}")
            ->assertStatus(422);
    }

    public function test_tracking_shows_collector_on_the_way(): void
    {
        $test = LabTest::factory()->create(['price' => 399]);
        $created = $this->postJson('/api/v1/bookings', [
            'test_ids' => [$test->id],
            'name' => 'Meena Iyer',
            'phone' => '9765432109',
            'patient_name' => 'Meena Iyer',
            'patient_age' => 52,
            'patient_gender' => 'female',
            'patient_phone' => '9765432109',
            'address' => '12, Lake View',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560001',
            'landmark' => 'Near Lake Park Gate',
            'latitude' => 12.9784,
            'longitude' => 77.6401,
        ])->assertCreated();
        $number = $created->json('data.booking_number');

        $admin = User::factory()->create();
        $h = ['Authorization' => 'Bearer '.$admin->createToken('t')->plainTextToken];

        $this->patchJson("/api/v1/admin/bookings/{$number}/status", ['status' => 'confirmed'], $h)->assertOk();
        $collectionUuid = $this->getJson("/api/v1/admin/bookings/{$number}", $h)->json('data.collection.id');
        $staff = \App\Models\Staff::factory()->create();
        $this->postJson("/api/v1/admin/collections/{$collectionUuid}/assign", ['staff_id' => $staff->uuid], $h)->assertOk();
        $this->patchJson("/api/v1/admin/collections/{$collectionUuid}/status", ['status' => 'on_the_way'], $h)->assertOk();

        // Before: no on-the-way node. After admin sets it: timeline carries it.
        $track = $this->getJson("/api/v1/bookings/track?booking_number={$number}&phone=9765432109")->assertOk();
        $statuses = collect($track->json('data.timeline'))->pluck('status')->all();
        $this->assertContains('on_the_way', $statuses);
    }

    public function test_tracking_hides_reports_until_ready(): void
    {
        $test = LabTest::factory()->create(['price' => 399]);
        $created = $this->postJson('/api/v1/bookings', [
            'test_ids' => [$test->id],
            'name' => 'Meena Iyer',
            'phone' => '9765432109',
            'patient_name' => 'Meena Iyer',
            'patient_age' => 52,
            'patient_gender' => 'female',
            'patient_phone' => '9765432109',
            'address' => '12, Lake View',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560001',
            'landmark' => 'Near Lake Park Gate',
            'latitude' => 12.9784,
            'longitude' => 77.6401,
        ])->assertCreated();
        $number = $created->json('data.booking_number');

        $admin = User::factory()->create();
        $h = ['Authorization' => 'Bearer '.$admin->createToken('t')->plainTextToken];
        \Illuminate\Support\Facades\Storage::fake('reports');

        $this->patchJson("/api/v1/admin/bookings/{$number}/status", ['status' => 'confirmed'], $h)->assertOk();
        $this->patchJson("/api/v1/admin/bookings/{$number}/status", ['status' => 'collection_assigned'], $h)->assertOk();
        $this->patchJson("/api/v1/admin/bookings/{$number}/status", ['status' => 'sample_collected'], $h)->assertOk();
        $this->patchJson("/api/v1/admin/bookings/{$number}/status", ['status' => 'testing_in_progress'], $h)->assertOk();
        $this->postJson("/api/v1/admin/bookings/{$number}/report", [
            'file' => \Illuminate\Http\UploadedFile::fake()->create('report.pdf', 120, 'application/pdf'),
        ], $h)->assertCreated();

        // Uploaded but NOT marked ready — patient must see nothing.
        $this->getJson("/api/v1/bookings/track?booking_number={$number}&phone=9765432109")
            ->assertOk()
            ->assertJsonPath('data.booking.reports', []);

        // Marked ready but NOT yet shared — still nothing (download unlocks on delivery).
        $this->patchJson("/api/v1/admin/bookings/{$number}/status", ['status' => 'report_ready'], $h)->assertOk();
        $this->getJson("/api/v1/bookings/track?booking_number={$number}&phone=9765432109")
            ->assertOk()
            ->assertJsonPath('data.booking.reports', []);

        // Admin shares on WhatsApp (auto-completes) — report appears.
        $reportUuid = $this->getJson("/api/v1/admin/bookings/{$number}", $h)->json('data.reports.0.id');
        $this->postJson("/api/v1/admin/reports/{$reportUuid}/share-whatsapp", [], $h)->assertOk();
        $this->getJson("/api/v1/bookings/track?booking_number={$number}&phone=9765432109")
            ->assertOk()
            ->assertJsonCount(1, 'data.booking.reports');
    }

    public function test_report_file_requires_valid_signature(): void
    {
        $admin = User::factory()->create();
        $booking = Booking::factory()->create();
        $report = Report::factory()->create([
            'booking_id' => $booking->id,
            'uploaded_by' => $admin->id,
            'file_path' => 'booking-1/sample.pdf',
        ]);

        // No signature -> forbidden
        $this->get('/api/v1/reports/file?path=booking-1/sample.pdf')->assertStatus(403);

        // Forged path with valid signature for another path -> 404, never leaks
        $url = URL::temporarySignedRoute('api.v1.reports.file', now()->addMinutes(10), ['path' => 'booking-1/missing.pdf']);
        $this->get($url)->assertStatus(404);
    }

    public function test_contact_query_flow(): void
    {
        $created = $this->postJson('/api/v1/contact-queries', [
            'name' => 'Asha',
            'phone' => '9812345678',
            'email' => 'asha@example.com',
            'subject' => 'Home collection timing',
            'message' => 'What are the morning slot timings for fasting tests?',
        ])->assertCreated();
        $uuid = $created->json('data.id');

        $admin = User::factory()->create();
        $token = $admin->createToken('t')->plainTextToken;

        $this->getJson('/api/v1/admin/contact-queries', ['Authorization' => "Bearer $token"])
            ->assertOk();

        $this->patchJson("/api/v1/admin/contact-queries/{$uuid}", [
            'status' => 'in_progress',
            'admin_notes' => 'Called back, slot explained.',
        ], ['Authorization' => "Bearer $token"])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');
    }
}
