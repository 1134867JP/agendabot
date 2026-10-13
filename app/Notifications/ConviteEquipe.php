<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Convite para entrar na equipe de um estabelecimento: em vez de o dono
 * inventar e repassar uma senha, a pessoa cria a própria pelo link.
 */
class ConviteEquipe extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(
        #[\SensitiveParameter] public string $token,
        public string $estabelecimento,
        public ?string $convidadoPor = null,
    ) {
        $this->onQueue('notifications');
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $app = config('app.name');
        $dias = intdiv((int) config('auth.passwords.convites.expire', 10080), 60 * 24);
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
            'convite' => 1,
        ]);

        $quem = $this->convidadoPor ? "{$this->convidadoPor} convidou você" : 'Você foi convidado(a)';

        return (new MailMessage)
            ->subject("Seu acesso à equipe: {$this->estabelecimento} — {$app}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("{$quem} para fazer parte da equipe de {$this->estabelecimento} no {$app}.")
            ->line("Seu login é este e-mail: {$notifiable->email}")
            ->action('Criar minha senha', $url)
            ->line("O link vale por {$dias} dias e só pode ser usado uma vez. Se expirar, peça um novo convite a quem cadastrou você.")
            ->line('Se você não esperava este convite, ignore este e-mail.')
            ->salutation("Equipe {$app}");
    }
}
