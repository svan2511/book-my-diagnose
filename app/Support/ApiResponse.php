<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Centralized API response envelope (dev-prd §7).
 *
 * Success: { success: true, message, data, meta }
 * Error:   { success: false, message, errors }
 */
trait ApiResponse
{
    protected function ok(mixed $data = [], string $message = 'OK', array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => (object) $meta,
        ], $status);
    }

    protected function created(mixed $data = [], string $message = 'Created successfully.'): JsonResponse
    {
        return $this->ok($data, $message, [], 201);
    }

    protected function fail(string $message = 'Something went wrong.', array $errors = [], int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => (object) $errors,
        ], $status);
    }
}
