<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * E-mail de redefinição de senha em português. Vai pela fila para uma
 * lentidão ou queda do SMTP não derrubar a tela de "Esqueci a senha"; o
 * worker tenta de novo antes de desistir.
 */
class RedefinirSenha extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(#[\SensitiveParameter] string $token)
    {
        parent::__construct($token);
        $this->onQueue('notifications');
    }

    public function toMail($notifiable): MailMessage
    {
        $minutos = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        $app = config('app.name');

        return (new MailMessage)
            ->subject("Redefinir sua senha — {$app}")
            ->greeting('Olá!')
            ->line("Recebemos um pedido para redefinir a senha da sua conta no {$app}.")
            ->action('Criar nova senha', $this->resetUrl($notifiable))
            ->line("O link vale por {$minutos} minutos e só pode ser usado uma vez.")
            ->line('Se você não pediu a troca de senha, ignore este e-mail: sua senha atual continua valendo.')
            ->salutation("Equipe {$app}");
    }
}
