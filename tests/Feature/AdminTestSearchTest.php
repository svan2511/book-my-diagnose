<?php

namespace Tests\Feature;

use App\Models\LabTest;
use App\Models\TestCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTestSearchTest extends TestCase
{
    use RefreshDatabase;

    private function headers(): array
    {
        $token = User::factory()->create()->createToken('t')->plainTextToken;

        return ['Authorization' => "Bearer $token"];
    }

    public function test_search_puts_exact_code_match_first(): void
    {
        LabTest::factory()->create(['code' => 'P999', 'name' => 'Something With CBC Inside The Name']);
        LabTest::factory()->create(['code' => 'CBC001', 'name' => 'Complete Blood Count (CBC)']);

        $res = $this->getJson('/api/v1/admin/tests?search=CBC&per_page=10', $this->headers());

        $res->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.code', 'CBC001');
    }

    public function test_search_is_case_insensitive(): void
    {
        LabTest::factory()->create(['code' => 'THY301', 'name' => 'Complete Thyroid Care']);

        $this->getJson('/api/v1/admin/tests?search=thy', $this->headers())
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'THY301');
    }

    public function test_list_filters_by_status_and_category(): void
    {
        $catA = TestCategory::factory()->create();
        $catB = TestCategory::factory()->create();
        LabTest::factory()->create(['code' => 'TST001', 'name' => 'Alpha Test', 'category_id' => $catA->id, 'status' => 'active']);
        LabTest::factory()->create(['code' => 'TST002', 'name' => 'Beta Test', 'category_id' => $catB->id, 'status' => 'inactive']);

        $h = $this->headers();

        $this->getJson('/api/v1/admin/tests?status=inactive', $h)
            ->assertOk()->assertJsonPath('meta.total', 1);

        // Numeric id, public uuid and slug must all work (admin UI sends uuids).
        $this->getJson("/api/v1/admin/tests?category_id={$catA->id}", $h)
            ->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson("/api/v1/admin/tests?category_id={$catA->uuid}", $h)
            ->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson("/api/v1/admin/tests?category_id={$catA->slug}", $h)
            ->assertOk()->assertJsonPath('meta.total', 1);
    }
}
