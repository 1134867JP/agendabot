<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'convite' => $request->boolean('convite'),
        ]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'convite' => ['nullable', 'boolean'],
        ]);

        // Convites usam o próprio broker (tabela e validade separadas); quem
        // aceita um convite comprova o e-mail ao abrir o link.
        $convite = $request->boolean('convite');

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::broker($convite ? 'convites' : null)->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request, $convite) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                if ($convite && ! $user->hasVerifiedEmail()) {
                    $user->markEmailAsVerified();
                }

                event(new PasswordReset($user));
            }
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        if ($status == Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', $convite
                ? 'Senha criada! Entre com seu e-mail e a senha que você acabou de definir.'
                : __($status));
        }

        throw ValidationException::withMessages([
            'email' => [$convite && $status === Password::INVALID_TOKEN
                ? 'Este convite expirou ou já foi usado. Peça um novo convite a quem cadastrou você.'
                : trans($status)],
        ]);
    }
}
