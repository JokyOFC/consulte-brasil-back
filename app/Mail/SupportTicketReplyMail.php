<?php

namespace App\Mail;

use Src\Modules\Support\Infrastructure\Persistence\Eloquent\Models\SupportTicketMessageModel;
use Src\Modules\Support\Infrastructure\Persistence\Eloquent\Models\SupportTicketModel;

final class SupportTicketReplyMail extends BrandedMailable
{
    public function __construct(
        public SupportTicketModel $ticket,
        public SupportTicketMessageModel $message,
        public bool $staffReply,
    ) {}

    protected function mailSubject(): string
    {
        if ($this->staffReply) {
            return "Nova resposta no chamado #{$this->ticket->id} — {$this->ticket->title}";
        }

        return "[Suporte] Resposta do cliente no chamado #{$this->ticket->id}";
    }

    protected function mailTitle(): string
    {
        return $this->staffReply ? 'Nova resposta da equipe' : 'Resposta do cliente';
    }

    protected function mailPreview(): string
    {
        return 'Há uma nova mensagem no chamado.';
    }

    protected function mailView(): string
    {
        return 'mail.support-ticket-reply';
    }

    protected function mailData(): array
    {
        return [
            'ticketTitle' => $this->ticket->title,
            'messageBody' => $this->message->body,
            'ticketUrl' => $this->staffReply
                ? url('/client/tickets/'.$this->ticket->id)
                : url('/admin/tickets/'.$this->ticket->id),
            'ctaLabel' => $this->staffReply ? 'Ver chamado' : 'Responder no painel',
        ];
    }
}
