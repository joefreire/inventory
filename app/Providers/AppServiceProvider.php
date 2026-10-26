<?php

namespace App\Providers;

use App\Models\Location;
use App\Models\Tag;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // As telas usam Bootstrap, nao Tailwind
        Paginator::useBootstrapFive();

        // AI calls cost money and take seconds: bounded per user (or IP)
        RateLimiter::for('ai', fn (Request $request) => Limit::perMinute((int) config('ai.rate_limit_per_minute', 20))
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
        // login brute force: per IP on top of the per e-mail+IP lock in LoginRequest
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // Lists every part form and the "Trocar caixa" sheet need, in one place
        View::composer(['items._fields', 'items.show'], function ($view) {
            $view->with('locations', Location::orderBy('path')->get(['id', 'name', 'path', 'parent_id']));
        });
        View::composer('items._fields', function ($view) {
            $view->with('tags', Tag::orderBy('name')->get(['id', 'name', 'color']));
        });
    }
}
