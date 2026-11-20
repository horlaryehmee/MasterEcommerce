<?php

namespace App\Providers;

use App\Support\Modules\ModuleRegistry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Loads optional modules and client extensions (see ModuleRegistry). A module
 * that fails to load is skipped and logged, so it can never take the shop down.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class, fn () => new ModuleRegistry(base_path()));

        foreach ($this->app->make(ModuleRegistry::class)->loadable() as $module) {
            try {
                if ($module['namespace'] && is_dir($module['path'].'/src')) {
                    $this->autoload(rtrim($module['namespace'], '\\').'\\', $module['path'].'/src/');
                }
                if (is_file($module['path'].'/vendor/autoload.php')) {
                    require_once $module['path'].'/vendor/autoload.php';
                }
                if ($module['provider'] && class_exists($module['provider'])) {
                    $this->app->register($module['provider']);
                }
            } catch (Throwable $e) {
                Log::error("Module {$module['slug']} could not be loaded and was skipped: {$e->getMessage()}");
            }
        }
    }

    public function boot(): void
    {
        foreach ($this->app->make(ModuleRegistry::class)->loadable() as $module) {
            try {
                $path = $module['path'];
                if (is_dir($path.'/database/migrations')) {
                    $this->loadMigrationsFrom($path.'/database/migrations');
                }
                if (is_dir($path.'/resources/views')) {
                    $this->loadViewsFrom($path.'/resources/views', $module['slug']);
                }
                if (is_file($path.'/routes/web.php') && ! $this->app->routesAreCached()) {
                    Route::middleware('web')->group($path.'/routes/web.php');
                }
            } catch (Throwable $e) {
                Log::error("Module {$module['slug']} failed to boot and was skipped: {$e->getMessage()}");
            }
        }
    }

    protected function autoload(string $prefix, string $dir): void
    {
        spl_autoload_register(function (string $class) use ($prefix, $dir) {
            if (str_starts_with($class, $prefix)) {
                $file = $dir.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
                if (is_file($file)) {
                    require $file;
                }
            }
        });
    }
}
