<?php

namespace App\Mail;

use Src\Modules\Support\Domain\ValueObject\SupportTicketStatus;
use Src\Modules\Support\Infrastructure\Persistence\Eloquent\Models\SupportTicketModel;

final class SupportTicketStatusChangedMail extends BrandedMailable
{
    public function __construct(
        public SupportTicketModel $ticket,
        public SupportTicketStatus $previous,
        public SupportTicketStatus $current,
    ) {}

    protected function mailSubject(): string
    {
        return "Status do chamado #{$this->ticket->id} atualizado";
    }

    protected function mailTitle(): string
    {
        return 'Status do chamado atualizado';
    }

    protected function mailPreview(): string
    {
        return "De {$this->previous->label()} para {$this->current->label()}.";
    }

    protected function mailView(): string
    {
        return 'mail.support-ticket-status';
    }

    protected function mailData(): array
    {
        return [
            'ticketTitle' => $this->ticket->title,
            'previousStatus' => $this->previous->label(),
            'currentStatus' => $this->current->label(),
            'ticketUrl' => url('/client/tickets/'.$this->ticket->id),
        ];
    }
}
