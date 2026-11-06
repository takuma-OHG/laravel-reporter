<?php

declare(strict_types=1);

namespace Takuma\LaravelReporter;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class ReporterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/takuma-reporter.php', 'takuma-reporter');
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/takuma-reporter.php' => config_path('takuma-reporter.php')], 'takuma-reporter-config');

        // Ohne web-Middleware (kein CSRF/Session) und für alle Methoden, damit POST & Co. 404 statt 405/419 liefern.
        Route::any('takuma-reporter', ReportController::class);
    }
}
