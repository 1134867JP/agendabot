<?php

namespace App\Support;

use App\Mail\ErroAplicacaoMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ErrorAlerter
{
    /**
     * Envia um e-mail de alerta para uma exceção não tratada, com throttle por
     * classe+mensagem para não inundar a caixa de entrada em caso de erro repetido.
     */
    public static function avisar(Throwable $e): void
    {
        $email = config('services.error_alerts.email');
        if (! $email) {
            return;
        }

        $chave = 'error_alert:'.md5(get_class($e).$e->getMessage().$e->getFile().$e->getLine());
        $minutos = (int) config('services.error_alerts.throttle_minutes', 15);

        if (Cache::has($chave)) {
            return;
        }
        Cache::put($chave, true, now()->addMinutes($minutos));

        Mail::to($email)->queue(new ErroAplicacaoMail(
            classe: get_class($e),
            mensagem: $e->getMessage(),
            arquivo: $e->getFile(),
            linha: $e->getLine(),
            trace: $e->getTraceAsString(),
            url: request()?->fullUrl(),
        ));
    }
}
