<?php

namespace App\Http\Middleware;

use App\Support\AdminConsumptionOverview;
use App\Support\AppSettings;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Src\Modules\Support\Infrastructure\Persistence\Eloquent\Models\SupportTicketModel;

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
            'marketingSiteUrl' => config('app.marketing_url'),
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
                'plain_secret' => fn () => $request->session()->get('plain_secret'),
                'payment' => fn () => $request->session()->get('payment'),
            ],
            'adminShell' => fn () => $request->user()?->role === 'admin'
                ? app(AdminConsumptionOverview::class)->headerPayload()
                : null,
            'unread_support_tickets' => fn () => $this->unreadSupportTicketsCount($request),
            'appTimezone' => config('app.timezone', 'America/Sao_Paulo'),
            'appDisplayTimezone' => config('app.display_timezone', 'America/Sao_Paulo'),
        ];
    }

    private function unreadSupportTicketsCount(Request $request): int
    {
        $user = $request->user();
        if ($user === null) {
            return 0;
        }

        try {
            if ($user->role === 'admin') {
                return SupportTicketModel::query()
                    ->where('last_reply_by_staff', false)
                    ->where(function ($q) {
                        $q->whereNull('staff_last_read_at')
                            ->orWhereColumn('last_reply_at', '>', 'staff_last_read_at');
                    })
                    ->count();
            }

            return SupportTicketModel::query()
                ->where('user_id', $user->id)
                ->where('last_reply_by_staff', true)
                ->whereNotNull('last_reply_at')
                ->where(function ($q) {
                    $q->whereNull('user_last_read_at')
                        ->orWhereColumn('last_reply_at', '>', 'user_last_read_at');
                })
                ->count();
        } catch (\Throwable) {
            // Tabela ainda não migrada / ambiente parcialmente provisionado.
            return 0;
        }
    }
}
