<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\User;
use App\Support\Installer;
use Database\Seeders\DemoSeeder;
use Database\Seeders\EssentialSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Setup wizard for a fresh upload: 1. server check, 2. database, 3. store and
 * admin account → installs everything and locks itself. See EnsureInstalled.
 */
class InstallController extends Controller
{
    public const EXTENSIONS = ['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'fileinfo', 'curl', 'zip', 'gd'];

    public function welcome(): View
    {
        $checks = Installer::requirements(base_path(), self::EXTENSIONS);

        return view('install.welcome', ['checks' => $checks, 'ready' => collect($checks)->every('ok'), 'app' => 'your online store']);
    }

    public function database(): View
    {
        return view('install.database', ['db' => [
            'host' => env('DB_HOST', 'localhost') === '127.0.0.1' ? 'localhost' : env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', 3306),
            'database' => env('DB_DATABASE'),
            'username' => env('DB_USERNAME'),
        ]]);
    }

    public function saveDatabase(Request $request): RedirectResponse
    {
        $db = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);

        if ($error = Installer::testDatabase($db)) {
            return back()->withInput($request->except('password'))->withErrors(['database' => $error]);
        }

        Installer::markInstalling(base_path());
        Installer::writeEnv(base_path(), [
            'DB_CONNECTION' => 'mysql', 'DB_HOST' => $db['host'], 'DB_PORT' => $db['port'],
            'DB_DATABASE' => $db['database'], 'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password'] ?? '',
        ]);

        return redirect()->to(Installer::detectUrl().'/install/site');
    }

    public function site(): View|RedirectResponse
    {
        if (! env('DB_DATABASE')) {
            return redirect()->to(Installer::detectUrl().'/install/database');
        }

        return view('install.site', ['url' => Installer::detectUrl(), 'kind' => 'store']);
    }

    public function finish(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'demo' => ['boolean'],
        ]);
        @set_time_limit(300);

        $base = base_path();
        $url = rtrim($data['url'], '/');
        $https = str_starts_with($url, 'https://');
        $steps = [];

        try {
            Installer::useDatabase(['host' => env('DB_HOST'), 'port' => env('DB_PORT'), 'database' => env('DB_DATABASE'), 'username' => env('DB_USERNAME'), 'password' => env('DB_PASSWORD')]);

            Artisan::call('migrate', ['--force' => true]);
            $steps[] = 'Created the database tables.';
            Artisan::call('db:seed', ['--class' => EssentialSeeder::class, '--force' => true]);
            $steps[] = 'Added the settings and standard pages.';

            if ($request->boolean('demo')) {
                DemoSeeder::$confirmed = true;
                Artisan::call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);
                $steps[] = 'Loaded the demo products.';
            }

            BusinessSetting::forgetResolved();
            BusinessSetting::current()->update(['business_name' => $data['site_name']]);

            User::query()->updateOrCreate(['email' => strtolower($data['email'])], ['name' => $data['name'], 'password' => $data['password']]);
            $steps[] = 'Created your administrator account.';

            $env = [
                'APP_NAME' => $data['site_name'], 'APP_URL' => $url, 'APP_ENV' => 'production', 'APP_DEBUG' => false,
                'SESSION_DRIVER' => 'database', 'SESSION_SECURE_COOKIE' => $https,
            ];
            $env += $this->linkUploads();
            Installer::writeEnv($base, $env);
            $steps[] = isset($env['PUBLIC_DISK_ROOT']) ? 'Set up image uploads (this host blocks shortcuts, so they go straight into public/storage).' : 'Set up image uploads.';

            Artisan::call('optimize:clear');
            Installer::markInstalled($base, ['app' => 'catalog', 'url' => $url]);
        } catch (Throwable $e) {
            report($e);

            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['install' => 'Installation stopped: '.Str::limit($e->getMessage(), 300).' Fix the problem and press Install again (it is safe to repeat).']);
        }

        return view('install.done', [
            'steps' => $steps, 'url' => $url, 'admin' => $url.'/admin', 'https' => $https,
            'cron' => '* * * * * php '.base_path('artisan').' schedule:run >> /dev/null 2>&1',
        ]);
    }

    /** Uploaded images need public/storage: a symlink, or a real folder where symlinks are blocked. */
    protected function linkUploads(): array
    {
        if (file_exists(public_path('storage'))) {
            return [];
        }
        try {
            Artisan::call('storage:link');
        } catch (Throwable) {
        }
        if (file_exists(public_path('storage'))) {
            return [];
        }
        @mkdir(public_path('storage'), 0755, true);

        return ['PUBLIC_DISK_ROOT' => public_path('storage')];
    }
}
