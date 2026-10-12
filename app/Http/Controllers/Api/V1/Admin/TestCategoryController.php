<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Resources\TestCategoryResource;
use App\Models\TestCategory;
use App\Services\AuditService;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TestCategoryController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $categories = TestCategory::withCount('tests')->orderBy('name')->get();

        return $this->ok(TestCategoryResource::collection($categories), 'Categories retrieved successfully.');
    }

    public function store(StoreCategoryRequest $request, AuditService $audit): JsonResponse
    {
        $data = $request->validated();
        $slug = Str::slug($data['name']);

        // Same name was deleted before (soft-deleted rows still hold the
        // unique slug) — bring it back instead of crashing on the constraint.
        $trashed = TestCategory::onlyTrashed()->where('slug', $slug)->first();
        if ($trashed) {
            $trashed->restore();
            $trashed->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'active',
            ]);

            $audit->log('category.restored', TestCategory::class, $trashed->id, null, $trashed->only(['name']), $request->user()->id);

            return $this->ok(new TestCategoryResource($trashed), 'Category restored successfully.');
        }

        if (TestCategory::where('slug', $slug)->exists()) {
            return $this->fail("Category '{$data['name']}' already exists.", [], 422);
        }

        try {
            $category = TestCategory::create([...$data, 'slug' => $slug]);
        } catch (QueryException $e) {
            // Race between the check above and the insert — same friendly answer.
            report($e);

            return $this->fail("Category '{$data['name']}' already exists.", [], 422);
        }

        $audit->log('category.created', TestCategory::class, $category->id, null, $category->only(['name']), $request->user()->id);

        return $this->created(new TestCategoryResource($category), 'Category created successfully.');
    }

    public function show(string $category): JsonResponse
    {
        return $this->ok(new TestCategoryResource($this->resolve($category)), 'Category retrieved successfully.');
    }

    public function update(StoreCategoryRequest $request, string $category, AuditService $audit): JsonResponse
    {
        $model = $this->resolve($category);
        $old = $model->only(['name', 'status']);
        $model->fill($request->validated());

        if ($model->isDirty('name')) {
            $base = Str::slug($model->name);
            $slug = $base;
            $i = 2;
            while (TestCategory::where('slug', $slug)->where('id', '!=', $model->id)->exists()) {
                $slug = "{$base}-{$i}";
                $i++;
            }
            $model->slug = $slug;
        }

        $model->save();

        $audit->log('category.updated', TestCategory::class, $model->id, $old, $model->only(['name', 'status']), $request->user()->id);

        return $this->ok(new TestCategoryResource($model), 'Category updated successfully.');
    }

    public function destroy(string $category, AuditService $audit, Request $request): JsonResponse
    {
        $model = $this->resolve($category);

        if ($model->tests()->exists()) {
            return $this->fail('Category has tests and cannot be deleted. Deactivate it instead.', [], 422);
        }

        $audit->log('category.deleted', TestCategory::class, $model->id, $model->only(['name']), null, $request->user()->id);
        $model->delete();

        return $this->ok([], 'Category deleted successfully.');
    }

    private function resolve(string $category): TestCategory
    {
        return TestCategory::where('uuid', $category)
            ->orWhere('slug', $category)
            ->firstOrFail();
    }
}
