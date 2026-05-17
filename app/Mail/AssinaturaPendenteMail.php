<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssinaturaPendenteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public \App\Models\Tenant $tenant) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '⚠️ Pagamento pendente — AgendaBot');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.assinatura-pendente');
    }

    public function attachments(): array
    {
        return [];
    }
}
