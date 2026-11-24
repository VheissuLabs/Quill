<?php

namespace App\Providers;

use App\Events\MembersChanged;
use App\Policies\RolePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRouteBindings();

        DevCommands::except('server');
        DevCommands::artisan('schedule:work', 'scheduler');

        Gate::policy(Role::class, RolePolicy::class);

        Passport::authorizationView('mcp.authorize');

        $this->broadcastRoleChanges();
    }

    protected function broadcastRoleChanges(): void
    {
        $announce = function (int|string|null $organizationId): void {
            if ($organizationId !== null) {
                broadcast(new MembersChanged((int) $organizationId))->toOthers();
            }
        };

        Event::listen([RoleAttachedEvent::class, RoleDetachedEvent::class], fn () => $announce(getPermissionsTeamId()));
        Event::listen(
            [PermissionAttachedEvent::class, PermissionDetachedEvent::class],
            fn (PermissionAttachedEvent|PermissionDetachedEvent $event) => $announce($event->model->getAttribute('organization_id')),
        );
        Role::created(fn (Role $role) => $announce($role->getAttribute('organization_id')));
        Role::updated(fn (Role $role) => $announce($role->getAttribute('organization_id')));
        Role::deleted(fn (Role $role) => $announce($role->getAttribute('organization_id')));
    }

    protected function configureRouteBindings(): void
    {
        Route::bind('notification', fn (string $id): DatabaseNotification => auth()->user()->notifications()->findOrFail($id));
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        JsonResource::withoutWrapping();

        Model::preventLazyLoading(
            ! app()->isProduction(),
        );

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
