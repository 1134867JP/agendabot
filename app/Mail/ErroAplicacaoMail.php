<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ErroAplicacaoMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $classe,
        public readonly string $mensagem,
        public readonly string $arquivo,
        public readonly int $linha,
        public readonly string $trace,
        public readonly ?string $url,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '🐛 Erro em produção — '.class_basename($this->classe));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.erro-aplicacao');
    }
}
