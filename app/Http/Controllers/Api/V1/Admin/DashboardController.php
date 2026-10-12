<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponse;

    public function summary(DashboardService $dashboard): JsonResponse
    {
        return $this->ok($dashboard->summary(), 'Dashboard summary retrieved successfully.');
    }

    public function analytics(Request $request, DashboardService $dashboard): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', 'in:daily,weekly,monthly,yearly'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        return $this->ok(
            $dashboard->analytics(
                $request->input('period', 'daily'),
                $request->input('date_from'),
                $request->input('date_to')
            ),
            'Analytics retrieved successfully.'
        );
    }
}
