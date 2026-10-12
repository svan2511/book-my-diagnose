<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\BookingResource;
use App\Models\Customer;
use App\Services\AuditService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $q = Customer::withCount('bookings')->latest();

        if ($request->filled('search')) {
            $s = '%'.str_replace(['%', '_'], '', $request->input('search')).'%';
            $q->where(fn ($w) => $w->where('name', 'like', $s)
                ->orWhere('phone', 'like', $s)
                ->orWhere('email', 'like', $s));
        }

        $page = $q->paginate(min((int) $request->input('per_page', 20), 100));

        return $this->ok(CustomerResource::collection($page->items()), 'Customers retrieved successfully.', [
            'current_page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function show(string $customer): JsonResponse
    {
        $model = Customer::where('uuid', $customer)
            ->orWhere('id', $customer)
            ->with(['bookings' => fn ($q) => $q->latest()->with(['items', 'latestReport'])])
            ->withCount('bookings')
            ->firstOrFail();

        return $this->ok([
            'customer' => new CustomerResource($model),
            'bookings' => BookingResource::collection($model->bookings),
        ], 'Customer retrieved successfully.');
    }

    public function update(UpdateCustomerRequest $request, string $customer, AuditService $audit): JsonResponse
    {
        $model = Customer::where('uuid', $customer)->orWhere('id', $customer)->firstOrFail();
        $old = $model->only(['name', 'email', 'gender', 'age']);

        $model->update($request->validated());

        $audit->log('customer.updated', Customer::class, $model->id, $old, $model->only(['name', 'email', 'gender', 'age']), $request->user()->id);

        return $this->ok(new CustomerResource($model->loadCount('bookings')), 'Customer updated successfully.');
    }
}
