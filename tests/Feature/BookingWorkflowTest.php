<?php

namespace Tests\Feature;

use App\Models\LabTest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private int $testId;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('reports');
        $admin = User::factory()->create();
        $this->token = $admin->createToken('t')->plainTextToken;
        $this->testId = LabTest::factory()->create(['price' => 499])->id;
    }

    private function headers(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    private function createBooking(): string
    {
        $res = $this->postJson('/api/v1/bookings', [
            'test_ids' => [$this->testId],
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
        ]);
        $res->assertCreated();

        return $res->json('data.booking_number');
    }

    private function setStatus(string $number, string $status)
    {
        return $this->patchJson("/api/v1/admin/bookings/{$number}/status", ['status' => $status], $this->headers());
    }

    public function test_full_booking_workflow(): void
    {
        $number = $this->createBooking();

        $this->setStatus($number, 'confirmed')->assertOk();
        $this->setStatus($number, 'collection_assigned')->assertOk();
        $this->setStatus($number, 'sample_collected')->assertOk();
        $this->setStatus($number, 'testing_in_progress')->assertOk();

        // report_ready without a report must fail
        $this->setStatus($number, 'report_ready')->assertStatus(422);

        // upload report
        $upload = $this->postJson("/api/v1/admin/bookings/{$number}/report", [
            'file' => UploadedFile::fake()->create('report.pdf', 120, 'application/pdf'),
        ], $this->headers());
        $upload->assertCreated();
        $reportUuid = $upload->json('data.id');

        $this->setStatus($number, 'report_ready')->assertOk()
            ->assertJsonPath('data.status', 'report_ready');

        // share via WhatsApp completes the booking
        $share = $this->postJson("/api/v1/admin/reports/{$reportUuid}/share-whatsapp", [], $this->headers());
        $share->assertOk()->assertJsonStructure(['data' => ['phone', 'message', 'url']]);
        $this->assertStringStartsWith('https://wa.me/919811112222', $share->json('data.url'));

        $this->getJson("/api/v1/admin/bookings/{$number}", $this->headers())
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        // Delivered booking: status share now carries the download link too.
        $after = $this->postJson("/api/v1/admin/bookings/{$number}/share-status", [], $this->headers());
        $after->assertOk();
        $this->assertStringContainsString('Download Report', $after->json('data.message'));
        $this->assertStringContainsString('/tracking?booking='.$number, urldecode($after->json('data.url')));
    }

    public function test_share_status_returns_tracking_link_for_current_status(): void
    {
        $number = $this->createBooking();
        $this->setStatus($number, 'confirmed')->assertOk();

        $share = $this->postJson("/api/v1/admin/bookings/{$number}/share-status", [], $this->headers());

        $share->assertOk()->assertJsonStructure(['data' => ['phone', 'message', 'url']]);
        $this->assertStringStartsWith('https://wa.me/919811112222', $share->json('data.url'));
        $this->assertStringContainsString('Booking Confirmed', $share->json('data.message'));
        $this->assertStringContainsString('/tracking?booking='.$number, urldecode($share->json('data.url')));
        // Undelivered booking: tracking link only, never a download link.
        $this->assertStringNotContainsString('Download Report', $share->json('data.message'));
    }

    public function test_invalid_status_jump_is_rejected(): void
    {
        $number = $this->createBooking();

        $this->setStatus($number, 'completed')->assertStatus(422);
        $this->setStatus($number, 'sample_collected')->assertStatus(422);
    }

    public function test_report_upload_rejects_non_pdf(): void
    {
        $number = $this->createBooking();

        $this->postJson("/api/v1/admin/bookings/{$number}/report", [
            'file' => UploadedFile::fake()->create('photo.jpg', 120, 'image/jpeg'),
        ], $this->headers())->assertStatus(422);
    }

    public function test_report_upload_rejected_before_testing_starts(): void
    {
        $number = $this->createBooking();

        $pdf = fn () => ['file' => UploadedFile::fake()->create('report.pdf', 120, 'application/pdf')];

        // Pending and confirmed bookings have no tested sample yet.
        $this->postJson("/api/v1/admin/bookings/{$number}/report", $pdf(), $this->headers())
            ->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->setStatus($number, 'confirmed')->assertOk();
        $this->postJson("/api/v1/admin/bookings/{$number}/report", $pdf(), $this->headers())
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        // Allowed once testing starts; re-upload on report_ready stays allowed.
        $this->setStatus($number, 'collection_assigned')->assertOk();
        $this->setStatus($number, 'sample_collected')->assertOk();
        $this->postJson("/api/v1/admin/bookings/{$number}/report", $pdf(), $this->headers())
            ->assertStatus(422);
        $this->setStatus($number, 'testing_in_progress')->assertOk();
        $this->postJson("/api/v1/admin/bookings/{$number}/report", $pdf(), $this->headers())
            ->assertCreated();
    }

    public function test_collection_assign_flow(): void
    {
        $number = $this->createBooking();
        $this->setStatus($number, 'confirmed')->assertOk();

        $booking = $this->getJson("/api/v1/admin/bookings/{$number}", $this->headers())->json('data');
        $collectionUuid = $booking['collection']['id'];

        $staff = \App\Models\Staff::factory()->create(['name' => 'Rajesh Sharma', 'phone' => '9810012345']);

        $this->postJson("/api/v1/admin/collections/{$collectionUuid}/assign", [
            'staff_id' => $staff->uuid,
        ], $this->headers())->assertOk()
            ->assertJsonPath('data.collection.assigned_to', 'Rajesh Sharma')
            ->assertJsonPath('data.collection.staff.phone', '9810012345')
            ->assertJsonStructure(['data' => ['whatsapp' => ['phone', 'message', 'url']]]);

        $this->patchJson("/api/v1/admin/collections/{$collectionUuid}/status", [
            'status' => 'on_the_way',
        ], $this->headers())->assertOk()->assertJsonPath('data.status', 'on_the_way');
    }

    public function test_dashboard_and_analytics(): void
    {
        $this->createBooking();

        $this->getJson('/api/v1/admin/dashboard', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.total_bookings', 1)
            ->assertJsonPath('data.total_revenue', 499);

        $res = $this->getJson('/api/v1/admin/analytics?period=daily', $this->headers())
            ->assertOk()
            ->assertJsonStructure(['data' => ['series', 'by_status', 'popular_tests']]);

        // Zero-filled: all 14 daily buckets present even with a single booking.
        $series = $res->json('data.series');
        $this->assertCount(14, $series);
        $this->assertEquals(1, collect($series)->sum('bookings'));
    }
}
