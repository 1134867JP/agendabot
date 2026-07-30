import { Head, router, useForm } from "@inertiajs/react";
import AppLayout from "@/Layouts/AppLayout";
import EmptyState from "@/Components/UI/EmptyState";
import { PageProps, PaginatedData } from "@/types";

interface TenantResumo {
    id: number;
    nome: string;
}

interface AgendamentoGlobal {
    id: number;
    cliente_nome: string;
    cliente_telefone: string;
    inicio: string | null;
    data_hora: string | null;
    fim: string | null;
    status: string;
    origem: string | null;
    tenant: TenantResumo;
    recurso: { id: number; nome: string } | null;
    profissional: { id: number; nome: string } | null;
    servico: { id: number; nome: string } | null;
}

interface Props extends PageProps {
    agendamentos: PaginatedData<AgendamentoGlobal>;
    tenants: TenantResumo[];
    filtros: {
        tenant_id?: string;
        data?: string;
        status?: string;
    };
}

const STATUS_LABEL: Record<string, string> = {
    confirmado: "Confirmado",
    agendado: "Confirmado",
    concluido: "Concluído",
    cancelado: "Cancelado",
};

const STATUS_CLASS: Record<string, string> = {
    confirmado: "badge-green",
    agendado: "badge-green",
    concluido: "badge-gray",
    cancelado: "badge-red",
};

function formatarData(data: string | null) {
    if (!data) return "—";

    return new Date(data).toLocaleString("pt-BR", {
        dateStyle: "short",
        timeStyle: "short",
        timeZone: "America/Sao_Paulo",
    });
}

export default function Agendamentos({
    agendamentos,
    tenants,
    filtros,
}: Props) {
    const form = useForm({
        tenant_id: filtros.tenant_id ?? "",
        data: filtros.data ?? "",
        status: filtros.status ?? "",
    });

    const filtrar = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(
            route("superadmin.agendamentos"),
            form.data,
            { preserveState: true, replace: true },
        );
    };

    const limpar = () => {
        form.setData({ tenant_id: "", data: "", status: "" });
        router.get(route("superadmin.agendamentos"), {}, { replace: true });
    };

    return (
        <AppLayout
            title="Agendamentos globais"
            subtitle="Auditoria de reservas de todos os tenants"
        >
            <Head title="Agendamentos globais" />

            <form
                onSubmit={filtrar}
                className="card mb-5 grid gap-3 p-4 md:grid-cols-[minmax(12rem,1fr)_minmax(10rem,0.7fr)_minmax(10rem,0.7fr)_auto]"
            >
                <div>
                    <label className="label mb-1" htmlFor="filtro-tenant">
                        Tenant
                    </label>
                    <select
                        id="filtro-tenant"
                        value={form.data.tenant_id}
                        onChange={(event) =>
                            form.setData("tenant_id", event.target.value)
                        }
                        className="input"
                    >
                        <option value="">Todos</option>
                        {tenants.map((tenant) => (
                            <option key={tenant.id} value={tenant.id}>
                                {tenant.nome}
                            </option>
                        ))}
                    </select>
                </div>

                <div>
                    <label className="label mb-1" htmlFor="filtro-data">
                        Data
                    </label>
                    <input
                        id="filtro-data"
                        type="date"
                        value={form.data.data}
                        onChange={(event) =>
                            form.setData("data", event.target.value)
                        }
                        className="input"
                    />
                </div>

                <div>
                    <label className="label mb-1" htmlFor="filtro-status">
                        Status
                    </label>
                    <select
                        id="filtro-status"
                        value={form.data.status}
                        onChange={(event) =>
                            form.setData("status", event.target.value)
                        }
                        className="input"
                    >
                        <option value="">Todos</option>
                        <option value="confirmado">Confirmado</option>
                        <option value="concluido">Concluído</option>
                        <option value="cancelado">Cancelado</option>
                    </select>
                </div>

                <div className="flex items-end gap-2">
                    <button type="submit" className="btn-primary min-h-11">
                        Filtrar
                    </button>
                    <button
                        type="button"
                        onClick={limpar}
                        className="btn-secondary min-h-11"
                    >
                        Limpar
                    </button>
                </div>
            </form>

            <div className="card overflow-hidden">
                {agendamentos.data.length === 0 ? (
                    <EmptyState
                        title="Nenhum agendamento encontrado"
                        description="Ajuste os filtros ou aguarde novas reservas dos tenants."
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr
                                    style={{
                                        borderBottom: "1px solid var(--border)",
                                        background: "var(--bg-surface-2)",
                                    }}
                                >
                                    {[
                                        "Tenant",
                                        "Cliente",
                                        "Responsável",
                                        "Serviço",
                                        "Início",
                                        "Origem",
                                        "Status",
                                    ].map((cabecalho) => (
                                        <th
                                            key={cabecalho}
                                            className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted"
                                        >
                                            {cabecalho}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {agendamentos.data.map((agendamento) => (
                                    <tr
                                        key={agendamento.id}
                                        className="table-row-hover"
                                        style={{
                                            borderBottom: "1px solid var(--border)",
                                        }}
                                    >
                                        <td className="px-4 py-3 font-medium text-primary">
                                            {agendamento.tenant.nome}
                                        </td>
                                        <td className="px-4 py-3">
                                            <p className="font-medium text-primary">
                                                {agendamento.cliente_nome}
                                            </p>
                                            <p className="text-xs text-muted">
                                                {agendamento.cliente_telefone}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3 text-secondary">
                                            {agendamento.profissional?.nome
                                                ?? agendamento.recurso?.nome
                                                ?? "—"}
                                        </td>
                                        <td className="px-4 py-3 text-secondary">
                                            {agendamento.servico?.nome ?? "—"}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-secondary">
                                            {formatarData(
                                                agendamento.inicio
                                                ?? agendamento.data_hora,
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-secondary">
                                            {agendamento.origem ?? "manual"}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={`badge ${
                                                    STATUS_CLASS[
                                                        agendamento.status
                                                    ] ?? "badge-gray"
                                                }`}
                                            >
                                                {STATUS_LABEL[
                                                    agendamento.status
                                                ] ?? agendamento.status}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {agendamentos.last_page > 1 && (
                    <div
                        className="flex gap-1 overflow-x-auto px-4 py-3"
                        style={{ borderTop: "1px solid var(--border)" }}
                    >
                        {agendamentos.links.map((link, index) => (
                            <button
                                key={index}
                                type="button"
                                disabled={!link.url}
                                onClick={() =>
                                    link.url && router.get(link.url)
                                }
                                className="min-h-10 shrink-0 rounded-lg border px-3 py-1.5 text-xs font-medium disabled:opacity-40"
                                style={
                                    link.active
                                        ? {
                                              background: "var(--accent)",
                                              borderColor: "var(--accent)",
                                              color: "white",
                                          }
                                        : {
                                              borderColor: "var(--border-strong)",
                                              color: "var(--text-2)",
                                          }
                                }
                                dangerouslySetInnerHTML={{
                                    __html: link.label,
                                }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
