<?php

declare(strict_types=1);

namespace Src\Modules\Support\Infrastructure\Http\Controllers\Client;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Modules\Support\Application\Service\SupportTicketThreadService;
use Src\Modules\Support\Domain\ValueObject\SupportTicketCategory;
use Src\Modules\Support\Infrastructure\Http\SupportTicketPresenter;
use Src\Modules\Support\Infrastructure\Persistence\Eloquent\Models\SupportTicketModel;

final class SupportTicketController
{
    public function index(Request $request): Response
    {
        $this->authorize($request, 'viewAny', SupportTicketModel::class);

        $status = (string) $request->query('status', 'all');
        $category = (string) $request->query('category', 'all');
        $q = trim((string) $request->query('q', ''));

        $tickets = SupportTicketModel::query()
            ->withCount('messages')
            ->where('user_id', $request->user()->id)
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
                        ->orWhere('body', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('last_reply_at')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (SupportTicketModel $t) => SupportTicketPresenter::listItem($t));

        return Inertia::render('client/tickets/index', [
            'tickets' => $tickets,
            'filters' => [
                'status' => $status,
                'category' => $category,
                'q' => $q,
            ],
            'categories' => SupportTicketCategory::options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('client/tickets/create', [
            'categories' => SupportTicketCategory::options(),
        ]);
    }

    public function store(Request $request, SupportTicketThreadService $thread): RedirectResponse
    {
        $this->authorize($request, 'create', SupportTicketModel::class);

        $data = $request->validate([
            'category' => ['required', 'in:technical,financial,questions'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        $ticket = $thread->open(
            user: $request->user(),
            category: SupportTicketCategory::from($data['category']),
            title: $data['title'],
            body: $data['body'],
            files: $request->file('attachments', []) ?? [],
        );

        return redirect()
            ->route('client.tickets.show', $ticket->id)
            ->with('success', 'Chamado aberto com sucesso.');
    }

    public function show(string $ticket, Request $request, SupportTicketThreadService $thread): Response
    {
        $model = SupportTicketModel::query()->findOrFail($ticket);
        $this->authorize($request, 'view', $model);

        $thread->markRead($model, asStaff: false);

        return Inertia::render('client/tickets/show', [
            'ticket' => SupportTicketPresenter::detail($model->fresh(['messages.user', 'messages.attachments', 'attachments', 'user']) ?? $model),
            'categories' => SupportTicketCategory::options(),
        ]);
    }

    public function reply(string $ticket, Request $request, SupportTicketThreadService $thread): RedirectResponse
    {
        $model = SupportTicketModel::query()->findOrFail($ticket);
        $this->authorize($request, 'reply', $model);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:20000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        $thread->reply(
            ticket: $model,
            author: $request->user(),
            body: $data['body'],
            isStaff: false,
            files: $request->file('attachments', []) ?? [],
        );

        return back()->with('success', 'Resposta enviada.');
    }

    private function authorize(Request $request, string $ability, mixed $arguments): void
    {
        if (! $request->user()->can($ability, $arguments)) {
            abort(403);
        }
    }
}
