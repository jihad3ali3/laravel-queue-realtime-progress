<?php

declare(strict_types=1);

namespace YogaMeleniawan\JobBatchingWithRealtimeProgress\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use YogaMeleniawan\JobBatchingWithRealtimeProgress\Http\Controllers\AssetController;
use YogaMeleniawan\JobBatchingWithRealtimeProgress\Http\Controllers\DemoController;
use YogaMeleniawan\JobBatchingWithRealtimeProgress\Http\Controllers\ProgressController;
use YogaMeleniawan\JobBatchingWithRealtimeProgress\Services\RealtimeJobService;
use YogaMeleniawan\JobBatchingWithRealtimeProgress\Support\FrontendConfig;
use YogaMeleniawan\JobBatchingWithRealtimeProgress\View\Components\Panel;
use YogaMeleniawan\JobBatchingWithRealtimeProgress\View\Components\Taskbar;

class RealtimeJobBatchProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__, 2).'/config/realtime-job-batch.php',
            'realtime-job-batch'
        );

        $this->app->singleton(RealtimeJobService::class);
        $this->app->singleton(FrontendConfig::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(dirname(__DIR__, 2).'/resources/views', 'realtime-job-batch');

        Blade::component(Taskbar::class, 'realtime-job-taskbar');
        Blade::component(Panel::class, 'realtime-job-panel');

        $this->publishes([
            dirname(__DIR__, 2).'/config/realtime-job-batch.php' => config_path('realtime-job-batch.php'),
        ], 'realtime-job-batch-config');

        $this->publishes([
            dirname(__DIR__, 2).'/resources/views' => resource_path('views/vendor/realtime-job-batch'),
        ], 'realtime-job-batch-views');

        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        Route::get('/realtime-job-batch/assets/{file}', [AssetController::class, 'show'])
            ->where('file', '[A-Za-z0-9._-]+')
            ->name('realtime-job-batch.assets');

        if (config('realtime-job-batch.demo.enabled') && config('app.env') !== 'production') {
            Route::middleware(['web'])
                ->get('/realtime-job-batch/demo', [DemoController::class, 'index'])
                ->name('realtime-job-batch.demo');
        }

        if (! config('realtime-job-batch.routes.enabled', true)) {
            return;
        }

        $middleware = config('realtime-job-batch.routes.middleware', ['web']);
        $prefix = trim((string) config('realtime-job-batch.routes.prefix', 'realtime-job-batches'), '/');

        Route::middleware($middleware)
            ->prefix($prefix)
            ->group(function (): void {
                Route::get('/', [ProgressController::class, 'index'])->name('realtime-job-batch.index');
                Route::post('/demo', [DemoController::class, 'start'])->name('realtime-job-batch.demo.start');
                Route::get('/{batchId}', [ProgressController::class, 'show'])->name('realtime-job-batch.show');
                Route::post('/{batchId}/cancel', [ProgressController::class, 'cancel'])->name('realtime-job-batch.cancel');
            });
    }
}
