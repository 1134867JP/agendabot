import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import ConfiguracoesLayout from '@/Layouts/ConfiguracoesLayout';
import { PageProps } from '@/types';
import { useConfirm } from '@/hooks/useConfirm';

interface UsuarioEquipe {
    id: number;
    name: string;
    email: string;
    papel: 'admin' | 'operador';
    ativo: boolean;
    membro_desde: string;
}

interface Props extends PageProps {
    usuarios: UsuarioEquipe[];
    meu_id: number;
}

const PAPEL_LABEL: Record<string, string> = {
    admin:    'Admin',
    operador: 'Atendente',
};
const PAPEL_BADGE: Record<string, string> = {
    admin:    'badge-blue',
    operador: 'badge-gray',
};

function Avatar({ nome, size = 36 }: { nome: string; size?: number }) {
    const COLORS = [
        ['#6366f1', 'rgba(99,102,241,0.15)'],
        ['#00a884', 'rgba(0,168,132,0.15)'],
        ['#f59e0b', 'rgba(245,158,11,0.15)'],
        ['#8b5cf6', 'rgba(139,92,246,0.15)'],
        ['#06b6d4', 'rgba(6,182,212,0.15)'],
    ];
    const [fg, bg] = COLORS[(nome.charCodeAt(0) || 0) % COLORS.length];
    const initials = nome.trim().split(/\s+/).slice(0, 2).map(w => w[0]?.toUpperCase() ?? '').join('');
    return (
        <div
            className="flex shrink-0 items-center justify-center rounded-full font-semibold"
            style={{ width: size, height: size, background: bg, color: fg, fontSize: size * 0.36 }}
        >
            {initials}
        </div>
    );
}

function fmtData(iso: string) {
    return new Date(iso).toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' });
}

export default function Equipe({ usuarios, meu_id }: Props) {
    const [showForm, setShowForm] = useState(false);
    const { confirm, modal: confirmModal } = useConfirm();

    const { data, setData, post, processing, errors, reset } = useForm({
        name:     '',
        email:    '',
        password: '',
        papel:    'operador' as 'admin' | 'operador',
    });

    const salvar = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('tenant.equipe.store'), {
            onSuccess: () => { reset(); setShowForm(false); },
        });
    };

    const remover = async (u: UsuarioEquipe) => {
        const ok = await confirm({
            title: 'Remover usuário?',
            message: `${u.name} perderá o acesso ao painel imediatamente.`,
            confirmLabel: 'Remover',
            variant: 'danger',
        });
        if (ok) router.delete(route('tenant.equipe.destroy', u.id));
    };

    const alternarAcesso = async (u: UsuarioEquipe) => {
        const acao = u.ativo ? 'bloquear' : 'reativar';
        const ok = await confirm({
            title: `${u.ativo ? 'Bloquear' : 'Reativar'} acesso?`,
            message: u.ativo
                ? `${u.name} não conseguirá mais entrar no painel até você reativar o acesso.`
                : `${u.name} poderá entrar novamente no painel com o mesmo e-mail e senha.`,
            confirmLabel: u.ativo ? 'Bloquear acesso' : 'Reativar acesso',
            variant: u.ativo ? 'danger' : 'default',
        });
        if (ok) router.patch(route('tenant.equipe.toggle-ativo', u.id));
    };

    return (
        <ConfiguracoesLayout title="Equipe" subtitle="Gerencie quem tem acesso ao painel">
            <Head title="Equipe" />

            {/* Header row */}
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm" style={{ color: 'var(--text-3)' }}>
                    {usuarios.length} membro{usuarios.length !== 1 ? 's' : ''}
                </p>
                {!showForm && (
                    <button
                        onClick={() => setShowForm(true)}
                        className="btn-primary justify-center py-2 text-sm"
                    >
                        <svg width={14} height={14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Adicionar atendente
                    </button>
                )}
            </div>

            {/* Add user form */}
            {showForm && (
                <div className="card mb-5 p-4 sm:p-5">
                    <div className="mb-4 flex items-center justify-between">
                        <h3 className="text-sm font-semibold text-primary">Novo atendente</h3>
                        <button
                            onClick={() => { setShowForm(false); reset(); }}
                            className="text-xs transition-colors hover:text-[var(--text-1)]"
                            style={{ color: 'var(--text-3)' }}
                        >✕ Cancelar</button>
                    </div>

                    <form onSubmit={salvar} className="space-y-3">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label htmlFor="equipe-name" className="label mb-1">Nome</label>
                                <input
                                    id="equipe-name"
                                    value={data.name}
                                    onChange={e => setData('name', e.target.value)}
                                    className="input"
                                    placeholder="Maria Silva"
                                    autoFocus
                                />
                                {errors.name && <p className="mt-1 text-xs text-red-400">{errors.name}</p>}
                            </div>
                            <div>
                                <label htmlFor="equipe-email" className="label mb-1">E-mail</label>
                                <input
                                    id="equipe-email"
                                    type="email"
                                    value={data.email}
                                    onChange={e => setData('email', e.target.value)}
                                    className="input"
                                    placeholder="maria@exemplo.com"
                                />
                                {errors.email && <p className="mt-1 text-xs text-red-400">{errors.email}</p>}
                            </div>
                            <div>
                                <label htmlFor="equipe-password" className="label mb-1">Senha</label>
                                <input
                                    id="equipe-password"
                                    type="password"
                                    value={data.password}
                                    onChange={e => setData('password', e.target.value)}
                                    className="input"
                                    placeholder="Mínimo 8 caracteres"
                                />
                                {errors.password && <p className="mt-1 text-xs text-red-400">{errors.password}</p>}
                            </div>
                            <div>
                                <label htmlFor="equipe-papel" className="label mb-1">Papel</label>
                                <select
                                    id="equipe-papel"
                                    value={data.papel}
                                    onChange={e => setData('papel', e.target.value as 'admin' | 'operador')}
                                    className="input"
                                >
                                    <option value="operador">Atendente — acessa agenda e conversas</option>
                                    <option value="admin">Admin — acesso total, incluindo equipe</option>
                                </select>
                            </div>
                        </div>

                        <div className="flex flex-col-reverse gap-2 pt-1 sm:flex-row sm:justify-end">
                            <button
                                type="button"
                                onClick={() => { setShowForm(false); reset(); }}
                                className="btn-secondary justify-center py-2 text-sm"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                disabled={processing}
                                className="btn-primary justify-center py-2 text-sm"
                            >
                                {processing ? 'Salvando…' : 'Adicionar'}
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* User list */}
            <div className="card overflow-hidden">
                {usuarios.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 py-16">
                        <svg width={20} height={20} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" style={{ color: 'var(--text-3)' }}>
                            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 7a4 4 0 110 8 4 4 0 010-8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
                        </svg>
                        <p className="text-sm font-medium text-primary">Nenhum atendente ainda</p>
                    </div>
                ) : (
                    <div className="divide-y" style={{ borderColor: 'var(--border)' }}>
                        {usuarios.map(u => (
                            <div key={u.id} className="flex items-start gap-3 px-4 py-3.5 sm:items-center sm:gap-3.5">
                                <Avatar nome={u.name} size={38} />

                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <p className="truncate text-sm font-medium text-primary">{u.name}</p>
                                        {u.id === meu_id && (
                                            <span className="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-medium" style={{ background: 'var(--bg-surface-2)', color: 'var(--text-3)' }}>
                                                Você
                                            </span>
                                        )}
                                    </div>
                                    <p className="mt-0.5 truncate text-xs" style={{ color: 'var(--text-3)' }}>{u.email}</p>
                                </div>

                                <div className="flex shrink-0 flex-col items-end gap-2 sm:flex-row sm:items-center sm:gap-3">
                                    <span className={`badge ${PAPEL_BADGE[u.papel] ?? 'badge-gray'}`}>
                                        {PAPEL_LABEL[u.papel] ?? u.papel}
                                    </span>
                                    <span className={`badge ${u.ativo ? 'badge-green' : 'badge-gray'}`}>
                                        {u.ativo ? 'Ativo' : 'Bloqueado'}
                                    </span>
                                    <span className="hidden text-xs sm:inline" style={{ color: 'var(--text-3)' }}>
                                        desde {fmtData(u.membro_desde)}
                                    </span>
                                    {u.id !== meu_id && <>
                                        <button
                                            onClick={() => alternarAcesso(u)}
                                            title={u.ativo ? 'Bloquear acesso' : 'Reativar acesso'}
                                            className={`flex h-10 items-center justify-center rounded-md px-3 text-xs font-medium transition-colors ${u.ativo ? 'text-amber-500 hover:bg-amber-400/10' : 'text-emerald-500 hover:bg-emerald-400/10'}`}
                                        >
                                            {u.ativo ? 'Bloquear' : 'Reativar'}
                                        </button>
                                        <button
                                            onClick={() => remover(u)}
                                            title="Remover da equipe"
                                            className="flex h-10 w-10 items-center justify-center rounded-md text-red-400/60 transition-colors hover:bg-red-400/10 hover:text-red-400"
                                        >
                                            <svg width={14} height={14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/>
                                            </svg>
                                        </button>
                                    </>}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {confirmModal}
        </ConfiguracoesLayout>
    );
}
