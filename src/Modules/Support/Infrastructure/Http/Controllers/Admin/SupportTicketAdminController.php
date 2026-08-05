<?php

declare(strict_types=1);

namespace Src\Modules\Support\Infrastructure\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Modules\Support\Application\Service\SupportTicketThreadService;
use Src\Modules\Support\Domain\ValueObject\SupportTicketCategory;
use Src\Modules\Support\Domain\ValueObject\SupportTicketStatus;
use Src\Modules\Support\Infrastructure\Http\SupportTicketPresenter;
use Src\Modules\Support\Infrastructure\Persistence\Eloquent\Models\SupportTicketModel;

final class SupportTicketAdminController
{
    public function index(Request $request): Response
    {
        $status = (string) $request->query('status', 'all');
        $category = (string) $request->query('category', 'all');
        $q = trim((string) $request->query('q', ''));

        $tickets = SupportTicketModel::query()
            ->with(['user:id,name,email'])
            ->withCount('messages')
            ->when(
                in_array($status, ['open', 'in_progress', 'closed'], true),
                fn ($query) => $query->where('status', $status),
            )
            ->when(
                in_array($category, ['technical', 'financial', 'questions'], true),
                fn ($query) => $query->where('category', $category),
            )
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('title', 'like', "%{$q}%")
                        ->orWhere('body', 'like', "%{$q}%")
                        ->orWhereHas('user', function ($userQuery) use ($q) {
                            $userQuery->where('name', 'like', "%{$q}%")
                                ->orWhere('email', 'like', "%{$q}%");
                        });
                });
            })
            ->orderByDesc('last_reply_at')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (SupportTicketModel $t) => SupportTicketPresenter::listItem($t, forAdmin: true));

        return Inertia::render('admin/tickets/index', [
            'tickets' => $tickets,
            'filters' => [
                'status' => $status,
                'category' => $category,
                'q' => $q,
            ],
            'categories' => SupportTicketCategory::options(),
        ]);
    }

    public function show(string $ticket, Request $request, SupportTicketThreadService $thread): Response
    {
        $model = SupportTicketModel::query()->findOrFail($ticket);
        $thread->markRead($model, asStaff: true);

        return Inertia::render('admin/tickets/show', [
            'ticket' => SupportTicketPresenter::detail($model->fresh(['messages.user', 'messages.attachments', 'attachments', 'user']) ?? $model),
            'statuses' => array_map(
                fn (SupportTicketStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                SupportTicketStatus::cases(),
            ),
        ]);
    }

    public function update(string $ticket, Request $request, SupportTicketThreadService $thread): RedirectResponse
    {
        $model = SupportTicketModel::query()->findOrFail($ticket);

        $data = $request->validate([
            'status' => ['required', 'in:open,in_progress,closed'],
        ]);

        $thread->updateStatus($model, SupportTicketStatus::from($data['status']));

        return back()->with('success', 'Status atualizado.');
    }

    public function reply(string $ticket, Request $request, SupportTicketThreadService $thread): RedirectResponse
    {
        $model = SupportTicketModel::query()->findOrFail($ticket);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:20000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        $thread->reply(
            ticket: $model,
            author: $request->user(),
            body: $data['body'],
            isStaff: true,
            files: $request->file('attachments', []) ?? [],
        );

        return back()->with('success', 'Resposta enviada.');
    }
}
