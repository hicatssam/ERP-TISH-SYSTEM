<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        /*
         * Disable Laravel's default /up Closure route.
         * The application owns /up through System\\HealthController instead,
         * keeping every registered route controller-backed and route-cache safe.
         */
        health: null,
    )
    ->withSchedule(function (Schedule $schedule) {
        // Check every hour for scheduled reports that are due.
        // withoutOverlapping() prevents a second invocation starting before the
        // first finishes, guarding against slow exports under a busy cron.
        $schedule->command('reports:send-scheduled')
            ->hourly()
            ->withoutOverlapping();

        // Run the registered expiry command once per day. Keeping the schedule
        // here gives Laravel 12 one canonical scheduling source.
        $schedule->command('inventory:check-expiry')
            ->dailyAt('08:00')
            ->withoutOverlapping(60)
            ->onOneServer();
    })
    ->withMiddleware(function (Middleware $middleware) {
        // Non-sensitive locale preference must be readable by browser JS and Laravel.
        $middleware->encryptCookies(except: ['dahab_menu_lang']);
        $middleware->alias([
            'password.changed' => \App\Http\Middleware\EnsurePasswordChanged::class,
            'location.scope'   => \App\Http\Middleware\CheckLocationScope::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (
            \Illuminate\Http\Exceptions\PostTooLargeException $exception,
            \Illuminate\Http\Request $request
        ) {
            if (! $request->is('products', 'products/*')) {
                return null;
            }

            $message = 'حجم الملف أو بيانات النموذج أكبر من المسموح. اختر صورة أصغر من 2 ميجابايت وأعد المحاولة.';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'errors' => ['image' => [$message]],
                ], 413);
            }

            return response()->view('errors.product-upload', ['message' => $message], 413);
        });
    })->create();
