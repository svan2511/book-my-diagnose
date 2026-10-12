<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateContactQueryRequest;
use App\Http\Resources\ContactQueryResource;
use App\Models\ContactQuery;
use App\Services\AuditService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactQueryController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $q = ContactQuery::latest();

        if ($request->filled('status')) {
            $q->where('status', $request->input('status'));
        }
        if ($request->filled('search')) {
            $s = '%'.str_replace(['%', '_'], '', $request->input('search')).'%';
            $q->where(fn ($w) => $w->where('name', 'like', $s)
                ->orWhere('phone', 'like', $s)
                ->orWhere('email', 'like', $s)
                ->orWhere('subject', 'like', $s));
        }

        $page = $q->paginate(min((int) $request->input('per_page', 20), 100));

        return $this->ok(ContactQueryResource::collection($page->items()), 'Queries retrieved successfully.', [
            'current_page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function show(string $query): JsonResponse
    {
        $model = ContactQuery::where('uuid', $query)->firstOrFail();

        return $this->ok(new ContactQueryResource($model), 'Query retrieved successfully.');
    }

    public function update(UpdateContactQueryRequest $request, string $query, AuditService $audit): JsonResponse
    {
        $model = ContactQuery::where('uuid', $query)->firstOrFail();
        $old = $model->only(['status', 'assigned_to']);

        $model->update($request->validated());

        $audit->log('contact.updated', ContactQuery::class, $model->id, $old, $model->only(['status', 'assigned_to']), $request->user()->id);

        return $this->ok(new ContactQueryResource($model), 'Query updated successfully.');
    }
}
