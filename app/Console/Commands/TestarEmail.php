<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envia um e-mail de teste na hora (sem fila) para validar o SMTP do
 * servidor: o mesmo caminho usado por redefinição de senha e confirmação
 * de cadastro.
 */
class TestarEmail extends Command
{
    protected $signature = 'mail:testar {email : Endereço que vai receber o teste}';

    protected $description = 'Envia um e-mail de teste e mostra se o SMTP está funcionando';

    public function handle(): int
    {
        $destino = (string) $this->argument('email');
        if (! filter_var($destino, FILTER_VALIDATE_EMAIL)) {
            $this->error('Informe um e-mail válido.');

            return self::INVALID;
        }

        $mailer = (string) config('mail.default');
        $this->line("Mailer: {$mailer}");
        $this->line('Servidor: '.(config("mail.mailers.{$mailer}.host") ?? '—').':'.(config("mail.mailers.{$mailer}.port") ?? '—'));
        $this->line('Remetente: '.config('mail.from.address'));

        if (in_array($mailer, ['log', 'array'], true)) {
            $this->error("MAIL_MAILER={$mailer}: o e-mail não sai do servidor. Configure o SMTP no .env (veja .env.production.example).");

            return self::FAILURE;
        }

        try {
            Mail::raw(
                'Este é um e-mail de teste do '.config('app.name').'. Se você recebeu, a redefinição de senha e a confirmação de cadastro também vão chegar.',
                fn ($message) => $message->to($destino)->subject('Teste de e-mail — '.config('app.name')),
            );
        } catch (Throwable $e) {
            $this->error('Falha ao enviar: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Enviado para {$destino}. Confira a caixa de entrada e o spam.");

        return self::SUCCESS;
    }
}
