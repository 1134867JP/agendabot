<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\ConviteEquipe;
use Illuminate\Support\Facades\Password;

class ConviteEquipeService
{
    /** Gera um link novo (invalidando o anterior) e envia o convite por e-mail. */
    public function enviar(User $user, string $estabelecimento, ?string $convidadoPor = null): void
    {
        $token = Password::broker('convites')->createToken($user);

        $user->notify(new ConviteEquipe($token, $estabelecimento, $convidadoPor));
    }

    /** Sem mailer real o convite só vai para o log e nunca chega à pessoa. */
    public static function emailConfigurado(): bool
    {
        return ! in_array((string) config('mail.default'), ['log', 'array'], true)
            && filled(config('mail.from.address'));
    }
}
