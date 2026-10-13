<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/** Confirmação de e-mail do cadastro, em português e pela fila. */
class VerificarEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct()
    {
        $this->onQueue('notifications');
    }

    public function toMail($notifiable): MailMessage
    {
        $app = config('app.name');

        return (new MailMessage)
            ->subject("Confirme seu e-mail — {$app}")
            ->greeting('Olá!')
            ->line("Falta só confirmar seu e-mail para ativar sua conta no {$app}.")
            ->action('Confirmar e-mail', $this->verificationUrl($notifiable))
            ->line('Se você não criou uma conta, ignore este e-mail.')
            ->salutation("Equipe {$app}");
    }
}
