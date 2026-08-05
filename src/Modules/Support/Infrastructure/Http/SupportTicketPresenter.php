<?php

declare(strict_types=1);

namespace Src\Modules\Support\Infrastructure\Http;

use Src\Modules\Support\Domain\ValueObject\SupportTicketCategory;
use Src\Modules\Support\Domain\ValueObject\SupportTicketStatus;
use Src\Modules\Support\Infrastructure\Persistence\Eloquent\Models\SupportTicketAttachmentModel;
use Src\Modules\Support\Infrastructure\Persistence\Eloquent\Models\SupportTicketMessageModel;
use Src\Modules\Support\Infrastructure\Persistence\Eloquent\Models\SupportTicketModel;

final class SupportTicketPresenter
{
    /** @return array<string, mixed> */
    public static function listItem(SupportTicketModel $ticket, bool $forAdmin = false): array
    {
        $unread = $forAdmin ? $ticket->isUnreadForStaff() : $ticket->isUnreadForClient();

        $row = [
            'id' => $ticket->id,
            'category' => $ticket->category,
            'category_label' => SupportTicketCategory::tryFrom($ticket->category)?->label() ?? $ticket->category,
            'title' => $ticket->title,
            'status' => $ticket->status,
            'status_label' => SupportTicketStatus::tryFrom($ticket->status)?->label() ?? $ticket->status,
            'messages_count' => (int) ($ticket->messages_count ?? $ticket->messages()->count()),
            'last_reply_at' => optional($ticket->last_reply_at)?->toIso8601String(),
            'created_at' => optional($ticket->created_at)?->toIso8601String(),
            'is_unread' => $unread,
            'is_new' => $unread,
        ];

        if ($forAdmin) {
            $row['client_name'] = $ticket->user?->name;
            $row['client_email'] = $ticket->user?->email;
        }

        return $row;
    }

    /** @return array<string, mixed> */
    public static function detail(SupportTicketModel $ticket): array
    {
        $ticket->loadMissing(['messages.user', 'messages.attachments', 'attachments', 'user']);

        $openingAttachments = $ticket->attachments
            ->whereNull('support_ticket_message_id')
            ->values()
            ->map(fn (SupportTicketAttachmentModel $a) => self::attachment($a))
            ->all();

        return [
            'id' => $ticket->id,
            'category' => $ticket->category,
            'category_label' => SupportTicketCategory::tryFrom($ticket->category)?->label() ?? $ticket->category,
            'title' => $ticket->title,
            'body' => $ticket->body,
            'status' => $ticket->status,
            'status_label' => SupportTicketStatus::tryFrom($ticket->status)?->label() ?? $ticket->status,
            'created_at' => optional($ticket->created_at)?->toIso8601String(),
            'closed_at' => optional($ticket->closed_at)?->toIso8601String(),
            'client_name' => $ticket->user?->name,
            'client_email' => $ticket->user?->email,
            'opening_attachments' => $openingAttachments,
            'messages' => $ticket->messages
                ->sortBy('created_at')
                ->values()
                ->map(function (SupportTicketMessageModel $m) {
                    return [
                        'id' => $m->id,
                        'body' => $m->body,
                        'is_staff' => $m->is_staff,
                        'author_name' => $m->user?->name ?? '—',
                        'created_at' => optional($m->created_at)?->toIso8601String(),
                        'attachments' => $m->attachments
                            ->map(fn (SupportTicketAttachmentModel $a) => self::attachment($a))
                            ->all(),
                    ];
                })->all(),
        ];
    }

    /** @return array<string, mixed> */
    public static function attachment(SupportTicketAttachmentModel $a): array
    {
        return [
            'id' => $a->id,
            'original_name' => $a->original_name,
            'mime_type' => $a->mime_type,
            'size' => (int) $a->size,
            'download_url' => route('support-tickets.attachments.download', [
                'ticket' => $a->support_ticket_id,
                'attachment' => $a->id,
            ]),
        ];
    }
}
