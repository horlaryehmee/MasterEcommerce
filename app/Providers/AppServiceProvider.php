<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One instance per request, so upload statistics can be reported back to the admin.
        $this->app->scoped(\App\Services\ImageStorage::class);
        // One cart per request, so its lines are loaded from the database only once.
        $this->app->scoped(\App\Services\Cart::class);
        // Zones and methods are loaded once per request.
        $this->app->scoped(\App\Services\Shipping::class);
    }

    public function boot(): void
    {
        // Catch N+1 queries during development.
        Model::preventLazyLoading(! $this->app->isProduction());

        // GET /up (for uptime monitors): fails with 500 unless the database answers
        // and storage is writable, i.e. the shop can actually take orders.
        Event::listen(DiagnosingHealth::class, function () {
            DB::select('select 1');
            if (! is_writable(storage_path('framework/sessions')) && ! is_writable(storage_path('framework'))) {
                throw new \RuntimeException('Storage is not writable.');
            }
        });

        // Behind cPanel/Cloudflare proxies, make generated links use https when APP_URL does.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(8)->letters()->numbers()
            : Password::min(8));

        // Email settings from Admin → Settings → Email, applied the first time mail is used.
        \App\Support\MailSettings::flush();
        $this->app->afterResolving('mail.manager', function () {
            if ($this->app->runningUnitTests() || \App\Support\Installer::isInstalled(base_path())) {
                \App\Support\MailSettings::apply();
            }
        });

        // Password reset emails link to the admin reset page.
        ResetPassword::createUrlUsing(fn (User $user, string $token) => route('admin.password.reset', [
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ]));
    }
}
