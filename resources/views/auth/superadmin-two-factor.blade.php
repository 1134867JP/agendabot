<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Verificação de segurança — Agendou</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f4f7f6; color: #17211d; }
        main { width: min(420px, calc(100% - 32px)); background: white; border: 1px solid #dfe8e4; border-radius: 18px; padding: 28px; box-shadow: 0 18px 55px rgba(20, 46, 35, .10); }
        h1 { margin: 0 0 8px; font-size: 1.45rem; }
        p { margin: 0 0 22px; color: #5d6d66; line-height: 1.55; }
        label { display: block; margin-bottom: 8px; font-weight: 650; }
        input { box-sizing: border-box; width: 100%; padding: 13px 14px; border: 1px solid #bdcbc5; border-radius: 10px; font: inherit; letter-spacing: .24em; }
        input:focus { outline: 3px solid #d8f3e7; border-color: #25845f; }
        button { width: 100%; margin-top: 14px; padding: 13px 16px; border: 0; border-radius: 10px; background: #197552; color: white; font: inherit; font-weight: 700; cursor: pointer; }
        .secondary { background: transparent; color: #31584a; border: 1px solid #cbd8d3; }
        .error { color: #b42318; margin: 8px 0 0; font-size: .9rem; }
        .status { color: #176b4c; margin-bottom: 14px; }
    </style>
</head>
<body>
<main>
    <h1>Confirme seu acesso</h1>
    <p>Enviamos um código de seis dígitos para <strong>{{ $email }}</strong>. Ele expira em 10 minutos.</p>

    @if (session('status'))
        <div class="status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('superadmin.two-factor.verify') }}">
        @csrf
        <label for="code">Código de segurança</label>
        <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" autofocus required>
        @error('code')
            <div class="error">{{ $message }}</div>
        @enderror
        <button type="submit">Verificar e continuar</button>
    </form>

    <form method="POST" action="{{ route('superadmin.two-factor.resend') }}">
        @csrf
        <button class="secondary" type="submit">Enviar novo código</button>
    </form>
</main>
</body>
</html>
