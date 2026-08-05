<?php

declare(strict_types=1);

namespace Src\Modules\Support\Application\Service;

use App\Mail\SupportTicketOpenedMail;
use App\Mail\SupportTicketReplyMail;
use App\Mail\SupportTicketStatusChangedMail;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Src\Modules\Support\Domain\ValueObject\SupportTicketCategory;
use Src\Modules\Support\Domain\ValueObject\SupportTicketStatus;
use Src\Modules\Support\Infrastructure\Persistence\Eloquent\Models\SupportTicketAttachmentModel;
use Src\Modules\Support\Infrastructure\Persistence\Eloquent\Models\SupportTicketMessageModel;
use Src\Modules\Support\Infrastructure\Persistence\Eloquent\Models\SupportTicketModel;
use Src\Shared\Application\Contracts\IdGenerator;

final readonly class SupportTicketThreadService
{
    public function __construct(
        private IdGenerator $ids,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     */
    public function open(
        User $user,
        SupportTicketCategory $category,
        string $title,
        string $body,
        array $files = [],
    ): SupportTicketModel {
        $now = now();

        $ticket = SupportTicketModel::query()->create([
            'id' => $this->ids->generate(),
            'user_id' => $user->id,
            'account_id' => $user->account_id,
            'category' => $category->value,
            'title' => $title,
            'body' => $body,
            'status' => SupportTicketStatus::Open->value,
            'last_reply_at' => $now,
            'last_reply_by_staff' => false,
            'user_last_read_at' => $now,
            'staff_last_read_at' => null,
            'closed_at' => null,
        ]);

        $this->storeAttachments($ticket, null, $files);
        $this->notifyOpened($ticket, $user);

        return $ticket->fresh(['attachments']) ?? $ticket;
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    public function reply(
        SupportTicketModel $ticket,
        User $author,
        string $body,
        bool $isStaff,
        array $files = [],
    ): SupportTicketMessageModel {
        $now = now();

        $message = SupportTicketMessageModel::query()->create([
            'id' => $this->ids->generate(),
            'support_ticket_id' => $ticket->id,
            'user_id' => $author->id,
            'body' => $body,
            'is_staff' => $isStaff,
        ]);

        $this->storeAttachments($ticket, $message, $files);

        $updates = [
            'last_reply_at' => $now,
            'last_reply_by_staff' => $isStaff,
        ];

        if ($isStaff) {
            if ($ticket->status === SupportTicketStatus::Open->value) {
                $updates['status'] = SupportTicketStatus::InProgress->value;
            }
            $updates['staff_last_read_at'] = $now;
        } else {
            if ($ticket->status === SupportTicketStatus::Closed->value) {
                $updates['status'] = SupportTicketStatus::Open->value;
                $updates['closed_at'] = null;
            }
            $updates['user_last_read_at'] = $now;
        }

        $ticket->fill($updates)->save();
        $this->notifyReply($ticket->fresh() ?? $ticket, $message, $author);

        return $message->load('attachments');
    }

    public function updateStatus(SupportTicketModel $ticket, SupportTicketStatus $status): SupportTicketModel
    {
        $previous = SupportTicketStatus::from($ticket->status);

        if ($previous === $status) {
            return $ticket;
        }

        $ticket->status = $status->value;
        $ticket->closed_at = $status === SupportTicketStatus::Closed ? now() : null;
        $ticket->save();

        $this->notifyStatusChanged($ticket, $previous, $status);

        return $ticket;
    }

    public function markRead(SupportTicketModel $ticket, bool $asStaff): void
    {
        if ($asStaff) {
            $ticket->staff_last_read_at = now();
        } else {
            $ticket->user_last_read_at = now();
        }

        $ticket->save();
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    private function storeAttachments(
        SupportTicketModel $ticket,
        ?SupportTicketMessageModel $message,
        array $files,
    ): void {
        foreach (array_slice($files, 0, 5) as $file) {
            $path = $file->store("support-tickets/{$ticket->id}", 'local');

            SupportTicketAttachmentModel::query()->create([
                'id' => $this->ids->generate(),
                'support_ticket_id' => $ticket->id,
                'support_ticket_message_id' => $message?->id,
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize() ?: 0,
            ]);
        }
    }

    private function notifyOpened(SupportTicketModel $ticket, User $client): void
    {
        $to = config('support.notify_email');
        if (! is_string($to) || $to === '') {
            return;
        }

        try {
            Mail::to($to)
                ->send(new SupportTicketOpenedMail($ticket, $client));
        } catch (\Throwable $e) {
            Log::warning('support.ticket.open_mail_failed', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyReply(SupportTicketModel $ticket, SupportTicketMessageModel $message, User $author): void
    {
        try {
            if ($message->is_staff) {
                $client = User::query()->find($ticket->user_id);
                if ($client !== null) {
                    Mail::to($client)->send(new SupportTicketReplyMail($ticket, $message, staffReply: true));
                }

                return;
            }

            $to = config('support.notify_email');
            if (is_string($to) && $to !== '') {
                Mail::to($to)->send(new SupportTicketReplyMail($ticket, $message, staffReply: false));
            }
        } catch (\Throwable $e) {
            Log::warning('support.ticket.reply_mail_failed', [
                'ticket_id' => $ticket->id,
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyStatusChanged(
        SupportTicketModel $ticket,
        SupportTicketStatus $previous,
        SupportTicketStatus $current,
    ): void {
        try {
            $client = User::query()->find($ticket->user_id);
            if ($client === null) {
                return;
            }

            Mail::to($client)->send(new SupportTicketStatusChangedMail($ticket, $previous, $current));
        } catch (\Throwable $e) {
            Log::warning('support.ticket.status_mail_failed', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
