<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateStaffRequest;
use App\Http\Resources\StaffResource;
use App\Models\Staff;
use App\Services\AuditService;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $q = Staff::withCount('activeCollections')->latest();

        if ($request->filled('search')) {
            $raw = str_replace(['%', '_'], '', $request->input('search'));
            $digits = preg_replace('/\D+/', '', $raw);
            $q->where(function ($w) use ($raw, $digits) {
                $w->where('name', 'like', '%'.$raw.'%');
                if ($digits !== '') {
                    $w->orWhere('phone', 'like', '%'.$digits.'%');
                }
            });
            // Exact phone match first, then name prefix, then the rest.
            $q->orderByRaw(
                'CASE WHEN phone = ? THEN 0 WHEN LOWER(name) LIKE LOWER(?) THEN 1 ELSE 2 END',
                [$digits, $raw.'%']
            );
        }
        if ($request->filled('status')) {
            $q->where('status', $request->input('status'));
        }
        if ($request->filled('role')) {
            $q->where('role', $request->input('role'));
        }

        $page = $q->paginate(min((int) $request->input('per_page', 20), 100));

        return $this->ok(StaffResource::collection($page->items()), 'Staff retrieved successfully.', [
            'current_page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function store(StoreStaffRequest $request, AuditService $audit): JsonResponse
    {
        $data = $request->validated();

        // Same phone was deleted before (soft-deleted rows hold the unique
        // phone) — bring it back instead of failing the constraint.
        $trashed = Staff::onlyTrashed()->where('phone', $data['phone'])->first();
        if ($trashed) {
            $trashed->restore();
            $trashed->update([
                'name' => $data['name'],
                'role' => $data['role'] ?? 'collector',
                'area' => $data['area'] ?? null,
                'status' => $data['status'] ?? 'active',
            ]);

            $audit->log('staff.restored', Staff::class, $trashed->id, null, $trashed->only(['name', 'phone']), $request->user()->id);

            return $this->ok(new StaffResource($trashed), 'Staff restored successfully.');
        }

        try {
            $staff = Staff::create($data);
        } catch (QueryException $e) {
            report($e);

            return $this->fail('A staff member with this phone number already exists.', [], 422);
        }

        $audit->log('staff.created', Staff::class, $staff->id, null, $staff->only(['name', 'phone']), $request->user()->id);

        return $this->created(new StaffResource($staff), 'Staff created successfully.');
    }

    public function show(string $staff): JsonResponse
    {
        $model = $this->resolve($staff);
        $model->loadCount('activeCollections');

        return $this->ok(new StaffResource($model), 'Staff retrieved successfully.');
    }

    public function update(UpdateStaffRequest $request, string $staff, AuditService $audit): JsonResponse
    {
        $model = $this->resolve($staff);
        $old = $model->only(['name', 'phone', 'status']);

        $model->update($request->validated());

        $audit->log('staff.updated', Staff::class, $model->id, $old, $model->only(['name', 'phone', 'status']), $request->user()->id);

        return $this->ok(new StaffResource($model->loadCount('activeCollections')), 'Staff updated successfully.');
    }

    public function destroy(string $staff, AuditService $audit, Request $request): JsonResponse
    {
        $model = $this->resolve($staff);

        if ($model->activeCollections()->exists()) {
            return $this->fail('Staff has active pickups and cannot be deleted. Reassign them first or deactivate instead.', [], 422);
        }

        $audit->log('staff.deleted', Staff::class, $model->id, $model->only(['name', 'phone']), null, $request->user()->id);
        $model->delete();

        return $this->ok([], 'Staff deleted successfully.');
    }

    private function resolve(string $staff): Staff
    {
        return Staff::where('uuid', $staff)->firstOrFail();
    }
}
