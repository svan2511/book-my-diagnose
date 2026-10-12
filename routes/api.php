<?php

use App\Http\Controllers\Api\V1\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Api\V1\Admin\CollectionController as AdminCollectionController;
use App\Http\Controllers\Api\V1\Admin\ContactQueryController as AdminContactQueryController;
use App\Http\Controllers\Api\V1\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Api\V1\Admin\StaffController as AdminStaffController;
use App\Http\Controllers\Api\V1\Admin\TestCategoryController as AdminTestCategoryController;
use App\Http\Controllers\Api\V1\Admin\TestController as AdminTestController;
use App\Http\Controllers\Api\V1\Auth\AdminAuthController;
use App\Http\Controllers\Api\V1\Public\BookingController as PublicBookingController;
use App\Http\Controllers\Api\V1\Public\ContactQueryController as PublicContactQueryController;
use App\Http\Controllers\Api\V1\Public\TestController as PublicTestController;
use Illuminate\Support\Facades\Route;

// All business APIs are versioned (dev-prd §4). No unversioned endpoints.
Route::prefix('v1')->name('api.v1.')->group(function () {

    // ---- Signed private file access (no auth header — signature IS the auth) ----
    Route::get('reports/file', [AdminReportController::class, 'file'])
        ->middleware('signed')
        ->name('reports.file');

    // ---- Public website ----
    Route::get('tests', [PublicTestController::class, 'index']);
    Route::get('tests/{test}', [PublicTestController::class, 'show']);
    Route::get('test-categories', [PublicTestController::class, 'categories']);

    Route::post('bookings', [PublicBookingController::class, 'store'])->middleware('throttle:public-booking');
    Route::get('bookings/track', [PublicBookingController::class, 'track'])->middleware('throttle:public-track');

    Route::post('contact-queries', [PublicContactQueryController::class, 'store'])->middleware('throttle:public-contact');

    // ---- Admin authentication (password + email OTP) ----
    Route::post('admin/login', [AdminAuthController::class, 'login'])->middleware('throttle:admin-login');
    Route::post('admin/verify-otp', [AdminAuthController::class, 'verifyOtp'])->middleware('throttle:admin-login');
    Route::post('admin/resend-otp', [AdminAuthController::class, 'resendOtp'])->middleware('throttle:admin-login');

    // ---- Admin (Sanctum auth + active staff gate in one middleware) ----
    Route::prefix('admin')->middleware(['admin'])->group(function () {
        Route::post('logout', [AdminAuthController::class, 'logout']);
        Route::get('me', [AdminAuthController::class, 'me']);

        Route::get('dashboard', [DashboardController::class, 'summary']);
        Route::get('analytics', [DashboardController::class, 'analytics']);

        Route::get('bookings', [AdminBookingController::class, 'index']);
        Route::get('bookings/{booking}', [AdminBookingController::class, 'show']);
        Route::patch('bookings/{booking}/status', [AdminBookingController::class, 'updateStatus']);
        Route::post('bookings/{booking}/share-status', [AdminBookingController::class, 'shareStatus']);
        Route::delete('bookings/{booking}', [AdminBookingController::class, 'destroy']);
        Route::post('bookings/{booking}/report', [AdminReportController::class, 'upload']);

        Route::get('tests', [AdminTestController::class, 'index']);
        Route::post('tests', [AdminTestController::class, 'store']);
        Route::get('tests/{test}', [AdminTestController::class, 'show']);
        Route::match(['put', 'patch'], 'tests/{test}', [AdminTestController::class, 'update']);
        Route::delete('tests/{test}', [AdminTestController::class, 'destroy']);
        Route::patch('tests/{test}/status', [AdminTestController::class, 'toggleStatus']);

        Route::get('test-categories', [AdminTestCategoryController::class, 'index']);
        Route::post('test-categories', [AdminTestCategoryController::class, 'store']);
        Route::get('test-categories/{category}', [AdminTestCategoryController::class, 'show']);
        Route::match(['put', 'patch'], 'test-categories/{category}', [AdminTestCategoryController::class, 'update']);
        Route::delete('test-categories/{category}', [AdminTestCategoryController::class, 'destroy']);

        Route::get('staff', [AdminStaffController::class, 'index']);
        Route::post('staff', [AdminStaffController::class, 'store']);
        Route::get('staff/{staff}', [AdminStaffController::class, 'show']);
        Route::match(['put', 'patch'], 'staff/{staff}', [AdminStaffController::class, 'update']);
        Route::delete('staff/{staff}', [AdminStaffController::class, 'destroy']);

        Route::get('customers', [AdminCustomerController::class, 'index']);
        Route::get('customers/{customer}', [AdminCustomerController::class, 'show']);
        Route::patch('customers/{customer}', [AdminCustomerController::class, 'update']);

        Route::get('collections', [AdminCollectionController::class, 'index']);
        Route::get('collections/{collection}', [AdminCollectionController::class, 'show']);
        Route::post('collections/{collection}/assign', [AdminCollectionController::class, 'assign']);
        Route::patch('collections/{collection}/status', [AdminCollectionController::class, 'updateStatus']);
        Route::post('collections/{collection}/share-whatsapp', [AdminCollectionController::class, 'shareWhatsApp']);

        Route::get('reports', [AdminReportController::class, 'index']);
        Route::post('reports/{report}/share-whatsapp', [AdminReportController::class, 'shareWhatsApp']);

        Route::get('contact-queries', [AdminContactQueryController::class, 'index']);
        Route::get('contact-queries/{query}', [AdminContactQueryController::class, 'show']);
        Route::patch('contact-queries/{query}', [AdminContactQueryController::class, 'update']);
    });
});
