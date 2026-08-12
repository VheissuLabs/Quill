<?php

namespace App\Providers;

use App\Contracts\PresenceLookup;
use App\Support\ReverbPresenceLookup;
use Carbon\CarbonImmutable;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\clear;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            PresenceLookup::class,
            ReverbPresenceLookup::class
        );

        DevCommands::except(
            'server',
        );
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->clearTerminalWhenStartingDev();
    }

    /**
     * `artisan dev` takes over the terminal with its own process tabs, so it reads
     * better starting from a clean screen than under whatever scrolled past before.
     */
    protected function clearTerminalWhenStartingDev(): void
    {
        Event::listen(function (CommandStarting $event): void {
            if ($event->command === 'dev') {
                clear();
            }
        });
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
