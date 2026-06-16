<?php

namespace App\Http\Middleware;

use App\Support\AdminConsumptionOverview;
use App\Support\AppSettings;
use Illuminate\Http\Request;
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
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                // Minutos de inatividade até o logout automático (ajustável pelo
                // admin). O front usa para um watcher de inatividade no cliente.
                'sessionTimeoutMinutes' => $request->user() !== null
                    ? AppSettings::sessionTimeoutMinutes()
                    : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'plain_token' => fn () => $request->session()->get('plain_token'),
                'payment' => fn () => $request->session()->get('payment'),
            ],
            'adminShell' => fn () => $request->user()?->role === 'admin'
                ? app(AdminConsumptionOverview::class)->headerPayload()
                : null,
        ];
    }
}
