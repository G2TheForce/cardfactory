<?php

namespace App\Console\Commands;

use App\Models\CardTheme;
use App\Support\Themes\ThemeLibrary;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ThemeSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Brings an existing install up to date after `git pull` (and `composer install`):
 * runs new migrations, syncs roles/permissions and the built-in theme library,
 * links storage and clears every cache. Safe to run as often as you like.
 */
class UpdateApp extends Command
{
    protected $signature = 'smartcard:update';

    protected $description = 'Apply migrations, sync roles and built-in themes, and clear caches after an update';

    public function handle(): int
    {
        $this->components->task('Running database migrations', fn () => $this->callSilently('migrate', ['--force' => true]) === 0);
        $this->components->task('Syncing roles and permissions', fn () => $this->callSilently('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]) === 0);
        $this->components->task('Syncing built-in card themes', fn () => $this->callSilently('db:seed', ['--class' => ThemeSeeder::class, '--force' => true]) === 0);

        if (! File::exists(public_path('storage'))) {
            $this->components->task('Linking public storage', fn () => $this->callSilently('storage:link') === 0);
        }

        $this->components->task('Clearing cached config, routes and views', fn () => $this->callSilently('optimize:clear') === 0);

        $library = count(ThemeLibrary::all());
        $installed = CardTheme::where('is_system', true)->count();
        $this->components->info("Built-in card themes installed: {$installed} of {$library}.");

        if (! File::exists(public_path('build/manifest.json'))) {
            $this->components->warn('Front-end assets are not built yet. Run: npm install, then npm run build');
        } elseif (File::lastModified(public_path('build/manifest.json')) < File::lastModified(resource_path('css/app.css'))) {
            $this->components->warn('Front-end assets are older than the source. Run: npm install, then npm run build');
        }

        $this->line('  Restart <comment>php artisan serve</comment> and the queue worker, then reload the browser with Ctrl+F5.');

        return self::SUCCESS;
    }
}
