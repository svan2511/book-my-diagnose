<?php

namespace App\Providers;

use App\Contracts\MediaStorageInterface;
use App\Contracts\Repositories\BookingRepositoryInterface;
use App\Contracts\Repositories\TestRepositoryInterface;
use App\Repositories\EloquentBookingRepository;
use App\Repositories\EloquentTestRepository;
use App\Services\Media\LocalMediaStorage;
use App\Services\WhatsApp\WaMeShareLink;
use App\Services\WhatsApp\WhatsAppShareInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TestRepositoryInterface::class, EloquentTestRepository::class);
        $this->app->bind(BookingRepositoryInterface::class, EloquentBookingRepository::class);
        // Swap LocalMediaStorage for a Cloudinary driver via env — no caller changes.
        $this->app->bind(MediaStorageInterface::class, LocalMediaStorage::class);
        // Swap WaMeShareLink for a WhatsApp Business API provider later.
        $this->app->bind(WhatsAppShareInterface::class, WaMeShareLink::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Abuse-sensitive public endpoints (dev-prd §56).
        RateLimiter::for('public-booking', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));
        RateLimiter::for('public-contact', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));
        RateLimiter::for('public-track', fn (Request $r) => Limit::perMinute(20)->by($r->ip()));
        RateLimiter::for('admin-login', fn (Request $r) => Limit::perMinute(8)->by($r->ip()));
    }
}
