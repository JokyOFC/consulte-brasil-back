<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

abstract class BrandedMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    abstract protected function mailSubject(): string;

    abstract protected function mailTitle(): string;

    abstract protected function mailPreview(): string;

    abstract protected function mailView(): string;

    /** @return array<string, mixed> */
    abstract protected function mailData(): array;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailSubject(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: $this->mailView(),
            with: array_merge([
                'subject' => $this->mailSubject(),
                'title' => $this->mailTitle(),
                'preview' => $this->mailPreview(),
            ], $this->mailData()),
        );
    }
}
