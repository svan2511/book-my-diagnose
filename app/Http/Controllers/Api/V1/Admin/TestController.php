<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTestRequest;
use App\Http\Requests\UpdateTestRequest;
use App\Http\Resources\TestResource;
use App\Models\LabTest;
use App\Models\TestCategory;
use App\Services\AuditService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $q = LabTest::with('category');

        if ($request->filled('search')) {
            $raw = str_replace(['%', '_'], '', $request->input('search'));
            $s = '%'.$raw.'%';
            $q->where(fn ($w) => $w->where('name', 'like', $s)->orWhere('code', 'like', $s));
            // Most relevant first: exact code → code prefix → name prefix → rest.
            // LOWER() keeps it case-insensitive on SQLite, MySQL and PostgreSQL alike.
            $q->orderByRaw(
                'CASE WHEN LOWER(code) = LOWER(?) THEN 0 WHEN LOWER(code) LIKE LOWER(?) THEN 1 WHEN LOWER(name) LIKE LOWER(?) THEN 2 ELSE 3 END',
                [$raw, $raw.'%', $raw.'%']
            );
        }
        if ($request->filled('status')) {
            $q->where('status', $request->input('status'));
        }
        if ($request->filled('category_id')) {
            // Accept numeric id, public uuid or slug (the admin UI sends uuids).
            $catParam = $request->input('category_id');
            if (! is_numeric($catParam)) {
                $catParam = TestCategory::where('uuid', $catParam)->orWhere('slug', $catParam)->value('id') ?? -1;
            }
            $q->where('category_id', $catParam);
        }

        $q->latest();

        $page = $q->paginate(min((int) $request->input('per_page', 20), 100));

        return $this->ok(TestResource::collection($page->items()), 'Tests retrieved successfully.', [
            'current_page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function store(StoreTestRequest $request, AuditService $audit): JsonResponse
    {
        $test = LabTest::create($request->validated());

        $audit->log('test.created', LabTest::class, $test->id, null, $test->only(['name', 'code', 'price']), $request->user()->id);

        return $this->created(new TestResource($test->load('category')), 'Test created successfully.');
    }

    public function show(string $test): JsonResponse
    {
        return $this->ok(new TestResource($this->resolve($test)->load('category')), 'Test retrieved successfully.');
    }

    public function update(UpdateTestRequest $request, string $test, AuditService $audit): JsonResponse
    {
        $model = $this->resolve($test);
        $old = $model->only(['name', 'code', 'price', 'status']);

        $model->update($request->validated());

        $audit->log('test.updated', LabTest::class, $model->id, $old, $model->only(['name', 'code', 'price', 'status']), $request->user()->id);

        return $this->ok(new TestResource($model->load('category')), 'Test updated successfully.');
    }

    public function destroy(string $test, AuditService $audit, Request $request): JsonResponse
    {
        $model = $this->resolve($test);

        if ($model->bookingItems()->exists()) {
            // Historical accuracy beats hard removal — deactivate instead.
            $model->update(['status' => 'inactive']);
            $audit->log('test.deactivated', LabTest::class, $model->id, ['status' => 'active'], ['status' => 'inactive'], $request->user()->id);

            return $this->ok(new TestResource($model->load('category')), 'Test has bookings, so it was deactivated instead of deleted.');
        }

        $audit->log('test.deleted', LabTest::class, $model->id, $model->only(['name', 'code']), null, $request->user()->id);
        $model->delete();

        return $this->ok([], 'Test deleted successfully.');
    }

    public function toggleStatus(string $test, AuditService $audit, Request $request): JsonResponse
    {
        $model = $this->resolve($test);
        $old = $model->status;
        $model->status = $old === 'active' ? 'inactive' : 'active';
        $model->save();

        $audit->log('test.status_changed', LabTest::class, $model->id, ['status' => $old], ['status' => $model->status], $request->user()->id);

        return $this->ok(new TestResource($model->load('category')), 'Test status updated successfully.');
    }

    private function resolve(string $test): LabTest
    {
        return LabTest::where('uuid', $test)
            ->orWhere('code', strtoupper($test))
            ->firstOrFail();
    }
}
