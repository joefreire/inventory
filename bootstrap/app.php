<?php

use App\Ai\Exceptions\AiException;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The AI is an external service: its failure is a 502 with a message the
        // screen shows ("A IA não respondeu: ..."), never a 500 page.
        $exceptions->render(function (AiException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'A IA não respondeu: '.$e->getMessage()], 502);
            }
        });
        $exceptions->report(function (AiException $e) {
            Log::warning('IA: '.$e->getMessage());

            return false;
        });
    })->create();
