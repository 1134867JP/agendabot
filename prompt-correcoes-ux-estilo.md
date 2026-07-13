# Prompt Complementar — Correções Cirúrgicas de UX + Estilo Unificado

> Aplicar sobre o projeto Agendou existente. Não reescrever o que já funciona —
> corrigir os três problemas específicos identificados e unificar o estilo visual.

---

## Problema 1 — Redirect automático para o painel do tenant

### Situação atual
O usuário faz login e cai em `/dashboard` que lista estabelecimentos + formulário
de criar novo. Para um dono com apenas um estabelecimento, essa tela não tem
utilidade — ele precisa ir direto para o painel.

### Correção no `DashboardController`

```php
// app/Http/Controllers/DashboardController.php

public function index(): Response|RedirectResponse
{
    $user = auth()->user();

    // Buscar todos os tenants do usuário
    $tenants = $user->tenants()->with('users')->get();

    // Se tem exatamente um tenant → ir direto para o painel
    if ($tenants->count() === 1) {
        session(['tenant_id' => $tenants->first()->id]);
        return redirect()->route('tenant.dashboard');
    }

    // Se não tem nenhum → onboarding
    if ($tenants->count() === 0) {
        return redirect()->route('onboarding.step1');
    }

    // Se tem mais de um (só super admin ou caso especial) → mostrar lista
    return Inertia::render('Dashboard', [
        'tenants' => $tenants,
    ]);
}
```

### Remover o formulário "Adicionar estabelecimento" do `Dashboard.tsx`

```tsx
// resources/js/Pages/Dashboard.tsx
// Esse componente só renderiza quando o usuário tem MÚLTIPLOS tenants
// (caso raro — super admin ou usuário com mais de um negócio)

// REMOVER completamente: o formulário "Adicionar estabelecimento"
// REMOVER: o título "Adicionar estabelecimento"
// MANTER apenas: lista de tenants para seleção

export default function Dashboard({ tenants }: { tenants: Tenant[] }) {
  return (
    <GuestLayout> {/* layout simples sem sidebar */}
      <div style={{ maxWidth: 480, margin: '80px auto', padding: '0 24px' }}>
        <h1>Selecione o estabelecimento</h1>
        {tenants.map(tenant => (
          <TenantCard key={tenant.id} tenant={tenant} />
        ))}
      </div>
    </GuestLayout>
  )
}
// Se o usuário tem um único tenant, ele NUNCA chega aqui.
```

---

## Problema 2 — Item ativo no menu lateral não acompanha a navegação

### Causa
O componente `Sidebar` provavelmente usa uma condição hardcoded ou não usa
`usePage().url` / `route().current()` corretamente.

### Correção no `Sidebar.tsx`

```tsx
// resources/js/Components/Layout/Sidebar.tsx

import { usePage } from '@inertiajs/react'

// Dentro do componente:
const { url } = usePage()

// Função para verificar se o item está ativo:
const isActive = (routeName: string): boolean => {
  // Usar route().current() do Ziggy
  return route().current(routeName) ?? false
}

// Alternativa sem Ziggy (por URL):
const isActiveByUrl = (path: string): boolean => {
  return url.startsWith(path)
}

// Aplicar na renderização de cada nav item:
const navItems = [
  { label: 'Dashboard', route: 'tenant.dashboard', path: '/painel',       icon: 'ti-layout-dashboard' },
  { label: 'Agenda',    route: 'tenant.agenda',    path: '/painel/agenda', icon: 'ti-calendar'         },
  { label: 'Agendamentos', route: 'tenant.agendamentos.index', path: '/painel/agendamentos', icon: 'ti-clock' },
  { label: 'Recursos',  route: 'tenant.recursos.index', path: '/painel/recursos', icon: 'ti-tools'     },
  { label: 'WhatsApp',  route: 'tenant.whatsapp',  path: '/painel/whatsapp', icon: 'ti-brand-whatsapp' },
]

// No JSX de cada item:
<Link
  href={route(item.route)}
  className={`nav-item ${isActiveByUrl(item.path) ? 'active' : ''}`}
>
  <i className={`ti ${item.icon}`} />
  {item.label}
</Link>

// IMPORTANTE: usar path mais específico primeiro para evitar falso-positivo
// '/painel' vai dar match em '/painel/agenda' também
// Solução: checar exatamente para o dashboard, startsWith para os outros

const isNavActive = (item: NavItem): boolean => {
  if (item.path === '/painel') {
    // Dashboard: ativo apenas na rota exata
    return url === '/painel' || url === '/painel/'
  }
  return url.startsWith(item.path)
}
```

---

## Problema 3 — Estilo do painel não combina com a landing page

### Direção visual unificada

**Paleta:**
```css
--bg-app:       #08090f;   /* fundo do app */
--bg-sidebar:   #0c0e16;   /* sidebar levemente diferente */
--bg-surface:   #111320;   /* cards e superfícies */
--bg-surface-2: #161929;   /* hover / campos */
--accent:       #6366f1;   /* indigo — botão primário */
--accent-light: rgba(99,102,241,0.12);
--emerald:      #6ee7b7;   /* status online, badges positivos */
--text-1:       #e8e6e1;   /* texto primário */
--text-2:       rgba(232,230,225,0.55); /* texto secundário */
--text-3:       rgba(232,230,225,0.25); /* texto terciário / labels */
--border:       rgba(255,255,255,0.07); /* bordas sutis */
--border-strong: rgba(255,255,255,0.13);
```

**Tipografia:**
```css
/* Carregar no <head> via Inertia (já deve estar) */
font-family display: 'Instrument Serif', serif  → títulos de página, números grandes
font-family corpo:   'DM Sans', sans-serif      → tudo o mais (peso 300-500)
```

### Atualizar `tailwind.config.ts`

```ts
import type { Config } from 'tailwindcss'

export default {
  content: ['./resources/**/*.{ts,tsx,blade.php}'],
  theme: {
    extend: {
      fontFamily: {
        sans:    ['DM Sans', 'sans-serif'],
        display: ['Instrument Serif', 'serif'],
      },
      colors: {
        app: {
          bg:       '#08090f',
          sidebar:  '#0c0e16',
          surface:  '#111320',
          surface2: '#161929',
        },
        accent: {
          DEFAULT: '#6366f1',
          light:   'rgba(99,102,241,0.12)',
          hover:   '#4f46e5',
        },
        emerald: '#6ee7b7',
        ink: {
          1: '#e8e6e1',
          2: 'rgba(232,230,225,0.55)',
          3: 'rgba(232,230,225,0.25)',
        },
        border: {
          DEFAULT: 'rgba(255,255,255,0.07)',
          strong:  'rgba(255,255,255,0.13)',
        },
      },
    },
  },
} satisfies Config
```

### Reescrever `AppLayout.tsx`

```tsx
// resources/js/Layouts/AppLayout.tsx

export default function AppLayout({ children }: Props) {
  const { auth, flash, trialDiasRestantes, subscriptionWarning, impersonando_tenant } = usePage().props as any

  return (
    <div className="flex h-screen overflow-hidden bg-app-bg text-ink-1">
      <Sidebar />
      <div className="flex flex-col flex-1 min-w-0 overflow-hidden">
        {impersonando_tenant && <ImpersonationBanner tenant={impersonando_tenant} />}
        {(trialDiasRestantes !== undefined || subscriptionWarning) && (
          <SubscriptionBanner
            diasTrial={trialDiasRestantes}
            warning={subscriptionWarning}
          />
        )}
        <main className="flex-1 overflow-y-auto p-6">
          {children}
        </main>
      </div>
    </div>
  )
}
```

### Reescrever `Sidebar.tsx` com o estilo da landing

```tsx
// resources/js/Components/Layout/Sidebar.tsx

const sections = [
  {
    label: 'Principal',
    items: [
      { label: 'Dashboard',     route: 'tenant.dashboard',         path: '/painel',             icon: 'ti-layout-dashboard' },
      { label: 'Agenda',        route: 'tenant.agenda',            path: '/painel/agenda',      icon: 'ti-calendar'         },
      { label: 'Agendamentos',  route: 'tenant.agendamentos.index', path: '/painel/agendamentos', icon: 'ti-clock'           },
    ],
  },
  {
    label: 'Configurar',
    items: [
      { label: 'Recursos',      route: 'tenant.recursos.index',    path: '/painel/recursos',    icon: 'ti-tools'            },
      { label: 'WhatsApp',      route: 'tenant.whatsapp',          path: '/painel/whatsapp',    icon: 'ti-brand-whatsapp'   },
      { label: 'Configurações', route: 'tenant.configuracoes',     path: '/painel/configuracoes', icon: 'ti-settings'       },
    ],
  },
]

// JSX:
<aside className="w-[200px] flex-shrink-0 flex flex-col bg-app-sidebar border-r border-border">

  {/* Logo */}
  <div className="px-5 py-5 border-b border-border">
    <span className="font-display text-xl text-white flex items-center gap-2">
      <span className="w-2 h-2 rounded-full bg-emerald inline-block" />
      Agendou
    </span>
    {/* Nome do tenant atual */}
    <span className="text-[11px] text-ink-3 mt-1 block truncate">
      {currentTenant?.nome}
    </span>
  </div>

  {/* Nav */}
  <nav className="flex-1 py-3 overflow-y-auto">
    {sections.map(section => (
      <div key={section.label} className="mb-4">
        <span className="px-5 text-[10px] font-medium uppercase tracking-widest text-ink-3 mb-1 block">
          {section.label}
        </span>
        {section.items.map(item => (
          <Link
            key={item.route}
            href={route(item.route)}
            className={[
              'flex items-center gap-2.5 px-5 py-2 text-[13px] transition-colors',
              'border-l-2',
              isNavActive(item)
                ? 'text-white bg-accent-light border-accent'
                : 'text-ink-2 border-transparent hover:text-ink-1 hover:bg-white/5',
            ].join(' ')}
          >
            <i className={`ti ${item.icon} text-[15px]`} aria-hidden />
            {item.label}
          </Link>
        ))}
      </div>
    ))}
  </nav>

  {/* Footer do sidebar: usuário + sair */}
  <div className="px-5 py-4 border-t border-border">
    <div className="text-[12px] text-ink-2 truncate mb-2">{auth.user.name}</div>
    <Link
      href={route('logout')}
      method="post"
      as="button"
      className="text-[11px] text-ink-3 hover:text-ink-2 transition-colors flex items-center gap-1.5"
    >
      <i className="ti ti-logout text-[13px]" aria-hidden />
      Sair
    </Link>
  </div>
</aside>
```

### Atualizar componentes de UI para o tema dark

#### `StatCard.tsx`
```tsx
// bg-app-surface border border-border rounded-xl p-4
// label: text-[11px] uppercase tracking-wider text-ink-3 font-medium
// value: font-display text-3xl text-white
// delta positivo: text-emerald-400  |  negativo: text-red-400
```

#### `DataTable.tsx`
```tsx
// wrapper: bg-app-surface border border-border rounded-xl overflow-hidden
// th: bg-app-surface2 text-[11px] uppercase tracking-wider text-ink-3 px-4 py-2.5
//     border-b border-border
// td: px-4 py-3 text-[13px] text-ink-1 border-b border-border
// tr hover: hover:bg-white/[0.02]
```

#### `Btn.tsx`
```tsx
// primary: bg-accent hover:bg-accent-hover text-white rounded-lg px-4 py-2 text-[13px] font-medium
// ghost:   border border-border-strong text-ink-2 hover:bg-white/5 rounded-lg px-4 py-2 text-[13px]
// danger:  bg-red-500/10 text-red-400 border border-red-500/20 hover:bg-red-500/15 rounded-lg px-4 py-2 text-[13px]
```

#### `Badge.tsx`
```tsx
const variants = {
  confirmado: 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20',
  cancelado:  'bg-red-500/10     text-red-400     border border-red-500/20',
  concluido:  'bg-white/5        text-ink-3       border border-border',
  whatsapp:   'bg-accent/10      text-accent      border border-accent/20',
  manual:     'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20',
  trial:      'bg-amber-500/10   text-amber-400   border border-amber-500/20',
}
// base: inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-medium
```

#### `PageHeader.tsx`
```tsx
// title: font-display text-2xl text-white  ← Instrument Serif
// subtitle: text-[13px] text-ink-3 mt-0.5
// wrapper: flex justify-between items-start mb-6
```

#### `ModalBase.tsx` (base para todos os modais)
```tsx
// overlay: fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4
// modal:   bg-app-surface border border-border rounded-2xl w-full max-w-md p-6
// header:  flex justify-between items-center mb-5
// title:   font-display text-xl text-white
// close:   text-ink-3 hover:text-ink-1 transition-colors
```

#### Inputs e Selects (global)
```tsx
// Aplicar via CSS global em app.css:
// input, select, textarea:
//   background: bg-app-surface2
//   border: 1px solid border-border-strong
//   color: text-ink-1
//   border-radius: rounded-lg
//   padding: px-3 py-2
//   font-size: text-[13px]
//   outline: none
//   focus: border-accent ring-1 ring-accent/30
//   placeholder: text-ink-3
```

---

## Problema 4 — Landing page: adicionar botão "Entrar"

### Navbar da landing (`Home.tsx` e `Precos.tsx`)

```tsx
// Adicionar na navbar, entre os links e o CTA de cadastro:

<nav className="flex items-center gap-6">
  <Link href="#como-funciona" className="text-[14px] text-white/50 hover:text-white/80 transition-colors">
    Como funciona
  </Link>
  <Link href={route('precos')} className="text-[14px] text-white/50 hover:text-white/80 transition-colors">
    Preços
  </Link>

  {/* Separador sutil */}
  <span className="w-px h-4 bg-white/10" />

  {/* Entrar — para quem já tem conta */}
  <Link
    href={route('login')}
    className="text-[14px] text-white/60 hover:text-white transition-colors font-medium"
  >
    Entrar
  </Link>

  {/* CTA principal */}
  <Link
    href={route('onboarding.step1')}
    className="bg-white text-[#08090f] rounded-lg px-4 py-2 text-[13px] font-medium
               hover:bg-white/90 transition-colors"
  >
    Começar grátis
  </Link>
</nav>
```

### Página de login (`Auth/Login.tsx`)

Manter o layout fora do painel (sem sidebar), mas com estilo dark:

```tsx
// Fundo: bg-app-bg min-h-screen
// Card centralizado: bg-app-surface border border-border rounded-2xl max-w-sm p-8
// Logo no topo: font-display text-2xl text-white com dot emerald
// Título: "Bem-vindo de volta"
// Subtítulo: "Entre na sua conta Agendou."
// Link embaixo: "Ainda não tem conta? Começar grátis →"
```

---

## Fluxo corrigido completo

```
Usuário acessa agendou.com
  → Navbar: [Como funciona] [Preços] | [Entrar] [Começar grátis]

Clica em "Entrar"
  → /login (dark, sem sidebar)
  → Faz login com sucesso

DashboardController::index()
  → Conta tenants do usuário
  → 0 tenants → /cadastro (onboarding)
  → 1 tenant  → seta session tenant_id → /painel (direto, sem tela intermediária)
  → N tenants → /dashboard (lista para escolher, sem formulário de criar)

Dentro do /painel
  → Sidebar com item ativo correto baseado na URL atual
  → Estilo dark unificado com a landing
```

---

## Checklist de entrega

- [ ] `DashboardController::index()` redireciona automaticamente quando há 1 tenant
- [ ] Página `/dashboard` só aparece para usuários com múltiplos tenants, sem formulário de criar
- [ ] Sidebar com `isNavActive()` baseado em `usePage().url` — sem falso-positivo no Dashboard
- [ ] `tailwind.config.ts` com paleta dark e fontes Instrument Serif + DM Sans
- [ ] `AppLayout.tsx` com fundo `bg-app-bg` e sidebar `bg-app-sidebar`
- [ ] `Sidebar.tsx` com estilo dark, logo com dot verde, nav item ativo com borda indigo
- [ ] `StatCard`, `DataTable`, `Badge`, `Btn`, `PageHeader`, `ModalBase` atualizados para dark
- [ ] Inputs e selects com estilo dark via CSS global
- [ ] Navbar da landing com link "Entrar" → `/login`
- [ ] Página de login com estilo dark (fundo escuro, card escuro)
