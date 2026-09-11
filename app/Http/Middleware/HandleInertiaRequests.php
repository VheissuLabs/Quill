<?php

namespace App\Http\Middleware;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'currentOrganization' => fn () => $user?->currentOrganization ? $user->toUserOrganization($user->currentOrganization) : null,
            'organizations' => fn () => $user?->toUserOrganizations(includeCurrent: true) ?? [],
            'currentTeam' => fn () => $this->currentTeam($user),
            'notifications' => fn () => $this->notifications($user),
            'availableTeams' => fn () => $user?->currentOrganization?->teams()
                ->orderBy('name')
                ->get()
                ->map(fn (Team $team) => ['id' => $team->id, 'name' => $team->name, 'slug' => $team->slug]) ?? [],
        ];
    }

    /**
     * Get the user's latest notifications for the top bar.
     *
     * @return array{unreadCount: int, items: array<int, array{id: string, title: string, body: string, url: string|null, readAt: string|null, createdAt: string}>}
     */
    private function notifications(?User $user): array
    {
        if (! $user) {
            return ['unreadCount' => 0, 'items' => []];
        }

        return [
            'unreadCount' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (DatabaseNotification $notification) => [
                    'id' => $notification->id,
                    'title' => (string) ($notification->data['title'] ?? ''),
                    'body' => (string) ($notification->data['body'] ?? ''),
                    'url' => $notification->data['url'] ?? null,
                    'readAt' => $notification->read_at?->toISOString(),
                    'createdAt' => $notification->created_at?->toISOString() ?? now()->toISOString(),
                ])
                ->all(),
        ];
    }

    /**
     * Get the team the user is working in within their current organization.
     *
     * @return array{id: int, name: string, slug: string}|null
     */
    private function currentTeam(?User $user): ?array
    {
        if (! $user?->currentOrganization) {
            return null;
        }

        $team = $user->currentTeamFor($user->currentOrganization);

        return $team ? ['id' => $team->id, 'name' => $team->name, 'slug' => $team->slug] : null;
    }
}
