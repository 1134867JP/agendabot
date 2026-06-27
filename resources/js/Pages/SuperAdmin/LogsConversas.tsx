import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';

interface Mensagem {
    id: number;
    tenant: string | null;
    telefone: string | null;
    remetente: 'cliente' | 'bot';
    conteudo: string;
    enviada_em: string | null;
}

interface Tenant { id: number; nome: string; }

export default function LogsConversas() {
    const [mensagens, setMensagens] = useState<Mensagem[]>([]);
    const [tenants,   setTenants]   = useState<Tenant[]>([]);
    const [loading,   setLoading]   = useState(true);
    const [tenantId,  setTenantId]  = useState('');
    const [telefone,  setTelefone]  = useState('');

    const buscar = async () => {
        setLoading(true);
        const params = new URLSearchParams();
        if (tenantId)  params.set('tenant_id', tenantId);
        if (telefone)  params.set('telefone',  telefone);
        try {
            const res  = await fetch(route('superadmin.logs.conversas') + (params.toString() ? '?' + params : ''), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();
            setMensagens(data.mensagens);
            setTenants(data.tenants);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { buscar(); }, []);

    return (
        <AppLayout title="Logs — Conversas do bot">
            <Head title="Conversas do bot" />

            {/* Tabs */}
            <div className="mb-5 flex gap-1 border-b" style={{ borderColor: 'var(--border)' }}>
                <a
                    href={route('superadmin.logs')}
                    className="px-4 py-2 text-sm font-medium border-b-2 border-transparent"
                    style={{ color: 'var(--text-3)' }}
                >
                    Sistema
                </a>
                <span
                    className="px-4 py-2 text-sm font-medium border-b-2 -mb-px"
                    style={{ borderColor: 'var(--accent)', color: 'var(--accent)' }}
                >
                    Conversas do bot
                </span>
            </div>

            {/* Filtros */}
            <div className="mb-4 flex flex-wrap gap-3">
                <select
                    value={tenantId}
                    onChange={e => setTenantId(e.target.value)}
                    className="rounded-lg border px-3 py-1.5 text-sm"
                    style={{ background: 'var(--bg-surface-2)', borderColor: 'var(--border)', color: 'var(--text-1)' }}
                >
                    <option value="">Todos os tenants</option>
                    {tenants.map(t => <option key={t.id} value={t.id}>{t.nome}</option>)}
                </select>
                <input
                    type="text"
                    placeholder="Filtrar por telefone..."
                    value={telefone}
                    onChange={e => setTelefone(e.target.value)}
                    onKeyDown={e => e.key === 'Enter' && buscar()}
                    className="rounded-lg border px-3 py-1.5 text-sm"
                    style={{ background: 'var(--bg-surface-2)', borderColor: 'var(--border)', color: 'var(--text-1)' }}
                />
                <button
                    onClick={buscar}
                    className="btn-primary text-sm"
                >
                    Filtrar
                </button>
                <span className="ml-auto text-xs self-center" style={{ color: 'var(--text-3)' }}>
                    {mensagens.length} mensagens (mais recentes primeiro)
                </span>
            </div>

            {/* Lista */}
            <div className="card divide-y">
                {loading && (
                    <div className="px-4 py-8 text-center text-sm" style={{ color: 'var(--text-3)' }}>Carregando...</div>
                )}
                {!loading && mensagens.length === 0 && (
                    <div className="px-4 py-8 text-center text-sm" style={{ color: 'var(--text-3)' }}>Nenhuma mensagem encontrada.</div>
                )}
                {mensagens.map(m => (
                    <div key={m.id} className="flex gap-3 px-4 py-3">
                        <div className="shrink-0 w-16 text-[10px] font-mono text-right pt-0.5" style={{ color: 'var(--text-3)' }}>
                            {m.enviada_em}
                        </div>
                        <div className="shrink-0">
                            <span
                                className="inline-block rounded px-1.5 py-0.5 text-[10px] font-bold"
                                style={m.remetente === 'cliente'
                                    ? { background: 'rgba(99,102,241,0.12)', color: '#a5b4fc' }
                                    : { background: 'rgba(52,211,153,0.1)',  color: '#34d399' }
                                }
                            >
                                {m.remetente === 'cliente' ? 'CLIENTE' : 'BOT'}
                            </span>
                        </div>
                        <div className="shrink-0 text-xs" style={{ color: 'var(--text-3)', minWidth: '110px' }}>
                            <div>{m.tenant ?? '—'}</div>
                            <div className="font-mono">{m.telefone}</div>
                        </div>
                        <div className="flex-1 text-sm whitespace-pre-wrap break-words" style={{ color: 'var(--text-1)' }}>
                            {m.conteudo}
                        </div>
                    </div>
                ))}
            </div>
        </AppLayout>
    );
}
