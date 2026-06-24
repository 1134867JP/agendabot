<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $codigo }} — {{ config('app.name', 'AgendaBot') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg-app:      #0e0e1a;
            --bg-surface:  #16162a;
            --border:      rgba(255,255,255,0.07);
            --accent:      #6366f1;
            --jade:        #00a884;
            --text-1:      #e8e6e1;
            --text-2:      rgba(232,230,225,0.65);
            --text-3:      rgba(232,230,225,0.35);
            --serif:       'Instrument Serif', Georgia, serif;
            --sans:        'DM Sans', system-ui, sans-serif;
        }

        body {
            font-family: var(--sans);
            background: var(--bg-app);
            color: var(--text-1);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            -webkit-font-smoothing: antialiased;
        }

        .logo {
            position: fixed;
            top: 1.5rem;
            left: 1.75rem;
            display: flex;
            align-items: center;
            gap: 0.625rem;
            text-decoration: none;
        }

        .logo-icon {
            width: 2rem; height: 2rem;
            background: var(--jade);
            border-radius: 0.625rem;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.875rem; font-weight: 700; color: white;
            flex-shrink: 0;
        }

        .logo-name {
            font-family: var(--serif);
            font-size: 1.125rem;
            color: var(--text-1);
        }

        .container {
            text-align: center;
            max-width: 28rem;
            width: 100%;
        }

        .error-code {
            font-family: var(--serif);
            font-size: clamp(5rem, 15vw, 8rem);
            line-height: 1;
            color: var(--text-1);
            letter-spacing: -0.04em;
            opacity: 0.12;
            user-select: none;
            margin-bottom: -1rem;
        }

        .icon-wrap {
            width: 4rem; height: 4rem;
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 1rem;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem;
        }

        .title {
            font-family: var(--serif);
            font-size: 1.75rem;
            font-weight: 400;
            color: var(--text-1);
            margin-bottom: 0.75rem;
        }

        .description {
            font-size: 0.9375rem;
            color: var(--text-2);
            line-height: 1.65;
            margin-bottom: 2rem;
        }

        .actions {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-primary {
            display: inline-flex; align-items: center; gap: 0.5rem;
            background: var(--accent);
            color: white;
            font-family: var(--sans);
            font-size: 0.875rem;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            text-decoration: none;
            transition: filter 0.15s;
        }
        .btn-primary:hover { filter: brightness(1.12); }

        .btn-secondary {
            display: inline-flex; align-items: center; gap: 0.5rem;
            background: transparent;
            color: var(--text-2);
            font-family: var(--sans);
            font-size: 0.875rem;
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            text-decoration: none;
            border: 1px solid var(--border);
            transition: border-color 0.15s, color 0.15s;
        }
        .btn-secondary:hover { border-color: rgba(255,255,255,0.18); color: var(--text-1); }

        .divider {
            width: 3rem; height: 1px;
            background: var(--border);
            margin: 0 auto 2rem;
        }
    </style>
</head>
<body>
    <a href="{{ url('/') }}" class="logo">
        <span class="logo-icon">A</span>
        <span class="logo-name">AgendaBot</span>
    </a>

    <div class="container">
        {{ $slot }}
    </div>
</body>
</html>
