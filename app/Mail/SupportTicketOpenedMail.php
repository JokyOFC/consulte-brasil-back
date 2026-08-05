<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Src\Modules\Support\Domain\ValueObject\SupportTicketCategory;
use Src\Modules\Support\Infrastructure\Persistence\Eloquent\Models\SupportTicketModel;

final class SupportTicketOpenedMail extends BrandedMailable
{
    public function __construct(
        public SupportTicketModel $ticket,
        public User $client,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailSubject(),
            replyTo: [new Address($this->client->email, $this->client->name)],
        );
    }

    protected function mailSubject(): string
    {
        $category = SupportTicketCategory::tryFrom($this->ticket->category)?->label() ?? $this->ticket->category;

        return '['.config('app.name')."] Novo ticket — {$category} — {$this->ticket->title}";
    }

    protected function mailTitle(): string
    {
        return 'Novo chamado de suporte';
    }

    protected function mailPreview(): string
    {
        return 'Um cliente abriu um novo ticket.';
    }

    protected function mailView(): string
    {
        return 'mail.support-ticket-opened';
    }

    protected function mailData(): array
    {
        $category = SupportTicketCategory::tryFrom($this->ticket->category)?->label() ?? $this->ticket->category;

        return [
            'clientName' => $this->client->name,
            'clientEmail' => $this->client->email,
            'category' => $category,
            'ticketTitle' => $this->ticket->title,
            'ticketBody' => $this->ticket->body,
            'ticketUrl' => url('/admin/tickets/'.$this->ticket->id),
        ];
    }
}
