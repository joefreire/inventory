<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(App\Http\Middleware\SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The AI is an external service: its failure is a 502 with a message the
        // screen shows ("A IA não respondeu: ..."), never a 500 page.
        $exceptions->render(function (App\Ai\Exceptions\AiException $e, Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'A IA não respondeu: '.$e->getMessage()], 502);
            }
        });
        $exceptions->report(function (App\Ai\Exceptions\AiException $e) {
            Illuminate\Support\Facades\Log::warning('IA: '.$e->getMessage());

            return false;
        });
    })->create();
