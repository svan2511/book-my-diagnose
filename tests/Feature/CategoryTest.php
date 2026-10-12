<?php

namespace Tests\Feature;

use App\Models\TestCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private function headers(): array
    {
        $token = User::factory()->create()->createToken('t')->plainTextToken;

        return ['Authorization' => "Bearer $token"];
    }

    public function test_duplicate_category_name_returns_friendly_error(): void
    {
        TestCategory::factory()->create(['name' => 'Allergy Panels', 'slug' => 'allergy-panels']);

        $res = $this->postJson('/api/v1/admin/test-categories', ['name' => 'Allergy Panels'], $this->headers());

        $res->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', "Category 'Allergy Panels' already exists.");

        // Raw DB internals must never leak to API consumers.
        $body = $res->getContent();
        $this->assertStringNotContainsStringIgnoringCase('SQLSTATE', $body);
        $this->assertStringNotContainsStringIgnoringCase('UNIQUE constraint', $body);
    }

    public function test_recreating_deleted_category_restores_it(): void
    {
        $cat = TestCategory::factory()->create(['name' => 'Anil', 'slug' => 'anil']);
        $cat->delete();
        $this->assertSoftDeleted('test_categories', ['id' => $cat->id]);

        $res = $this->postJson('/api/v1/admin/test-categories', ['name' => 'Anil'], $this->headers());

        $res->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('test_categories', ['id' => $cat->id, 'deleted_at' => null]);
        $this->assertEquals(1, TestCategory::withTrashed()->where('slug', 'anil')->count());
    }

    public function test_category_crud_roundtrip(): void
    {
        $h = $this->headers();

        $created = $this->postJson('/api/v1/admin/test-categories', ['name' => 'Trial Panels'], $h)
            ->assertCreated()
            ->assertJsonPath('success', true);
        $id = $created->json('data.id');

        $this->patchJson("/api/v1/admin/test-categories/$id", ['name' => 'Trial Panels Renamed'], $h)
            ->assertOk()
            ->assertJsonPath('data.slug', 'trial-panels-renamed');

        $this->deleteJson("/api/v1/admin/test-categories/$id", [], $h)->assertOk();
        $this->assertSoftDeleted('test_categories', ['slug' => 'trial-panels-renamed']);
    }
}
