<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactQueryRequest;
use App\Http\Resources\ContactQueryResource;
use App\Models\ContactQuery;
use App\Services\AuditService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ContactQueryController extends Controller
{
    use ApiResponse;

    public function store(StoreContactQueryRequest $request, AuditService $audit): JsonResponse
    {
        $query = ContactQuery::create(array_merge($request->validated(), ['status' => 'new']));

        $audit->log('contact.created', ContactQuery::class, $query->id);

        return $this->created(new ContactQueryResource($query), 'Message received. Our team will contact you soon.');
    }
}
