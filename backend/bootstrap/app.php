<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            require __DIR__.'/../routes/admin.php';
        },
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Fire due scheduled bulk emails. Requires the system cron to run
        // `php artisan schedule:run` every minute in production.
        $schedule->command('emails:process-scheduled')->everyMinute()->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->appendToGroup('api', \App\Http\Middleware\ApiLogger::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
