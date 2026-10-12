<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Contracts\Repositories\TestRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\TestCategoryResource;
use App\Http\Resources\TestResource;
use App\Models\LabTest;
use App\Models\TestCategory;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestController extends Controller
{
    use ApiResponse;

    public function index(Request $request, TestRepositoryInterface $tests): JsonResponse
    {
        $page = $tests->catalog($request->only([
            'search', 'category_id', 'category_slug', 'min_price', 'max_price', 'sort', 'per_page',
        ]));

        return $this->ok(
            TestResource::collection($page->items()),
            'Tests retrieved successfully.',
            [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ]
        );
    }

    public function show(string $test): JsonResponse
    {
        $model = LabTest::with('category')->active()
            ->where('uuid', $test)
            ->orWhere('code', strtoupper($test))
            ->firstOrFail();

        return $this->ok(new TestResource($model), 'Test retrieved successfully.');
    }

    public function categories(): JsonResponse
    {
        $categories = TestCategory::active()
            ->withCount(['tests' => fn ($q) => $q->active()->homeCollectible()])
            ->orderBy('name')
            ->get();

        return $this->ok(TestCategoryResource::collection($categories), 'Categories retrieved successfully.');
    }
}
