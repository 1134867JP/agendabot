<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SuperAdminTwoFactorController extends Controller
{
    public function challenge(Request $request): View|RedirectResponse
    {
        if ($this->verificado($request)) {
            return redirect()->intended(route('superadmin.dashboard'));
        }

        $this->enviarSeNecessario($request);

        return view('auth.superadmin-two-factor', [
            'email' => $this->mascararEmail((string) $request->user()->email),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $registro = Cache::get($this->cacheKey($request));
        if (! is_array($registro) || ! Hash::check($data['code'], (string) ($registro['hash'] ?? ''))) {
            throw ValidationException::withMessages([
                'code' => 'Código inválido ou expirado.',
            ]);
        }

        Cache::forget($this->cacheKey($request));
        $request->session()->regenerate();
        $request->session()->put('superadmin_2fa_verified_at', now()->timestamp);

        return redirect()->intended(route('superadmin.dashboard'));
    }

    public function resend(Request $request): RedirectResponse
    {
        Cache::forget($this->cacheKey($request));
        $this->enviarSeNecessario($request);

        return back()->with('status', 'Um novo código foi enviado.');
    }

    private function enviarSeNecessario(Request $request): void
    {
        if (Cache::has($this->cacheKey($request))) {
            return;
        }

        $code = (string) random_int(100000, 999999);
        Cache::put($this->cacheKey($request), [
            'hash' => Hash::make($code),
        ], now()->addMinutes(10));

        Mail::raw(
            "Seu código de acesso ao Super Admin do Agendou é {$code}. Ele expira em 10 minutos.",
            fn ($message) => $message
                ->to($request->user()->email)
                ->subject('Código de segurança — Agendou'),
        );
    }

    private function verificado(Request $request): bool
    {
        $verifiedAt = (int) $request->session()->get('superadmin_2fa_verified_at', 0);

        return $verifiedAt > 0 && $verifiedAt >= now()->subHours(8)->timestamp;
    }

    private function cacheKey(Request $request): string
    {
        return 'security:superadmin-2fa:'.$request->user()->id.':'.hash('sha256', $request->session()->getId());
    }

    private function mascararEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));

        return $visible.str_repeat('*', max(2, mb_strlen($local) - mb_strlen($visible))).'@'.$domain;
    }
}
