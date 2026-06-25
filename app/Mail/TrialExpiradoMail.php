<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrialExpiradoMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Tenant $tenant) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '⏰ Seu período de teste encerrou — AgendaBot');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.trial-expirado');
    }

    public function attachments(): array
    {
        return [];
    }
}
