<?php

namespace Tests\Feature;

use App\Models\LabTest;
use App\Models\TestCategory;
use App\Models\User;
use Database\Seeders\RateListSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTestStatusTest extends TestCase
{
    use RefreshDatabase;

    private function headers(): array
    {
        $token = User::factory()->create()->createToken('t')->plainTextToken;

        return ['Authorization' => "Bearer $token"];
    }

    public function test_list_exposes_status_and_flags(): void
    {
        LabTest::factory()->create(['code' => 'TST010', 'status' => 'inactive']);

        $res = $this->getJson('/api/v1/admin/tests?search=TST010', $this->headers());

        $res->assertOk()
            ->assertJsonPath('data.0.status', 'inactive')
            ->assertJsonStructure(['data' => [['status', 'is_nabl', 'is_outsourced', 'is_discontinued']]]);
    }

    public function test_toggle_flips_status_both_ways(): void
    {
        $h = $this->headers();
        $test = LabTest::factory()->create(['code' => 'TST011', 'status' => 'active']);

        $this->patchJson("/api/v1/admin/tests/{$test->uuid}/status", [], $h)
            ->assertOk()->assertJsonPath('data.status', 'inactive');

        $this->patchJson("/api/v1/admin/tests/{$test->uuid}/status", [], $h)
            ->assertOk()->assertJsonPath('data.status', 'active');
    }

    public function test_edit_can_set_nabl_and_outsourced_flags(): void
    {
        $h = $this->headers();
        $test = LabTest::factory()->create(['code' => 'TST013']);

        $this->patchJson("/api/v1/admin/tests/{$test->uuid}", ['is_nabl' => true, 'is_outsourced' => true], $h)
            ->assertOk()
            ->assertJsonPath('data.is_nabl', true)
            ->assertJsonPath('data.is_outsourced', true);

        $this->postJson('/api/v1/admin/tests', [
            'name' => 'Flagged Test', 'code' => 'TST014', 'category_id' => $test->category_id,
            'price' => 100, 'is_nabl' => true,
        ], $h)->assertCreated()->assertJsonPath('data.is_nabl', true);
    }

    public function test_update_with_unchanged_code_passes(): void
    {
        $h = $this->headers();
        $test = LabTest::factory()->create(['code' => 'TST020', 'name' => 'Original Name']);

        // Frontend always sends the (uppercased) code, even when untouched.
        $this->patchJson("/api/v1/admin/tests/{$test->uuid}", ['code' => 'TST020', 'name' => 'Renamed'], $h)
            ->assertOk()
            ->assertJsonPath('data.code', 'TST020')
            ->assertJsonPath('data.name', 'Renamed');
    }

    public function test_update_with_truly_duplicate_code_fails(): void
    {
        $h = $this->headers();
        LabTest::factory()->create(['code' => 'TST021']);
        $other = LabTest::factory()->create(['code' => 'TST022']);

        $this->patchJson("/api/v1/admin/tests/{$other->uuid}", ['code' => 'TST021'], $h)
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_long_package_names_within_1000_chars_pass(): void
    {
        $h = $this->headers();
        $test = LabTest::factory()->create(['code' => 'TST023']);
        $long = rtrim(str_repeat('Very Long Panel Name Component ', 12)); // 371 chars

        $this->patchJson("/api/v1/admin/tests/{$test->uuid}", ['name' => $long], $h)
            ->assertOk()
            ->assertJsonPath('data.name', $long);

        $this->postJson('/api/v1/admin/tests', [
            'name' => $long, 'code' => 'TST024', 'category_id' => $test->category_id, 'price' => 100,
        ], $h)->assertCreated();
    }

    public function test_edit_can_set_status_explicitly(): void
    {
        $h = $this->headers();
        $test = LabTest::factory()->create(['code' => 'TST012', 'status' => 'active']);

        $this->patchJson("/api/v1/admin/tests/{$test->uuid}", ['status' => 'inactive'], $h)
            ->assertOk()->assertJsonPath('data.status', 'inactive');
    }

    public function test_reseed_preserves_admin_status_and_category_moves(): void
    {
        $this->seed(RateListSeeder::class);

        $victim = LabTest::where('code', 'A154')->firstOrFail();
        $otherCat = TestCategory::where('slug', 'hematology')->firstOrFail();

        // Admin moves + deactivates the test by hand.
        $victim->update(['category_id' => $otherCat->id, 'status' => 'inactive']);

        $this->seed(RateListSeeder::class);

        $victim->refresh();
        $this->assertEquals($otherCat->id, $victim->category_id);
        $this->assertEquals('inactive', $victim->status);
    }
}
