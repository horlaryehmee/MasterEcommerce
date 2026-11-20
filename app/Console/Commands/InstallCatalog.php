<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\EssentialSeeder;
use Illuminate\Console\Command;
use Throwable;

/** One-step installation for a new client website. */
class InstallCatalog extends Command
{
    protected $signature = 'catalog:install
        {--demo : Also load the Nexa Stores demo content}
        {--no-admin : Skip creating the administrator account}';

    protected $description = 'Install the catalog: migrate, essential setup, storage link and administrator account';

    public function handle(): int
    {
        if (blank(config('app.key'))) {
            $this->error('APP_KEY is missing. Run: php artisan key:generate');

            return self::FAILURE;
        }

        $this->components->task('Running database migrations', fn () => $this->callSilently('migrate', ['--force' => true]) === 0);
        $this->components->task('Creating settings and standard pages', fn () => $this->callSilently('db:seed', ['--class' => EssentialSeeder::class, '--force' => true]) === 0);

        $this->components->task('Linking public storage', function () {
            if (file_exists(public_path('storage'))) {
                return true;
            }
            try {
                return $this->callSilently('storage:link') === 0 && file_exists(public_path('storage'));
            } catch (Throwable) {
                return false;
            }
        });

        if (! file_exists(public_path('storage'))) {
            $this->warn('Could not create public/storage. See README → "Uploaded images on hosts without symlinks".');
        }

        if ($this->option('demo')) {
            $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);
        }

        if (! $this->option('no-admin') && ! User::query()->exists()) {
            $this->newLine();
            $this->info('Create the administrator account:');
            $this->call('catalog:create-admin');
        }

        $this->newLine();
        $this->info('Installation complete. Open '.url('/admin').' to configure the business.');

        return self::SUCCESS;
    }
}
