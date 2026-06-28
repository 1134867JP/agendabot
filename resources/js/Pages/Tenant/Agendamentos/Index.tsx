import { Head, router, useForm } from "@inertiajs/react";
import { useState, useEffect, useRef } from "react";
import AppLayout from "@/Layouts/AppLayout";
import { PageProps, Agendamento, Recurso, PaginatedData } from "@/types";
import { useConfirm } from "@/hooks/useConfirm";

interface Props extends PageProps {
    agendamentos: PaginatedData<Agendamento>;
    recursos: Recurso[];
    filtros: {
        data?: string;
        recurso_id?: string;
        status?: string;
        busca?: string;
    };
}

const STATUS_BADGE: Record<string, string> = {
    confirmado: "badge-green",
    agendado: "badge-green",
    cancelado: "badge-red",
    concluido: "badge-gray",
};
const STATUS_LABEL: Record<string, string> = {
    confirmado: "Confirmado",
    agendado: "Confirmado",
    cancelado: "Cancelado",
    concluido: "Concluído",
};

function fmtDt(iso: string) {
    return new Date(iso).toLocaleString("pt-BR", {
        day: "2-digit",
        month: "2-digit",
        year: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
        timeZone: "America/Sao_Paulo",
    });
}
function duracaoMin(a: string, b: string) {
    return Math.round((new Date(b).getTime() - new Date(a).getTime()) / 60000);
}

// ─── Modal de edição ─────────────────────────────────────────────────────────

function toLocalInput(iso: string) {
    if (!iso) return "";
    return new Date(iso)
        .toLocaleString("sv-SE", { timeZone: "America/Sao_Paulo" })
        .slice(0, 16)
        .replace(" ", "T");
}

function EditarReservaModal({
    agendamento,
    recursos,
    onClose,
}: {
    agendamento: Agendamento;
    recursos: Recurso[];
    onClose: () => void;
}) {
    const { data, setData, put, processing, errors } = useForm({
        recurso_id: (agendamento.recurso?.id ?? "") as number | string,
        cliente_nome: agendamento.cliente_nome,
        cliente_telefone: agendamento.cliente_telefone,
        inicio: toLocalInput(agendamento.inicio),
        fim: toLocalInput(agendamento.fim),
        observacoes: agendamento.observacoes ?? "",
        status: agendamento.status,
    });

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.key === "Escape") onClose();
        };
        document.addEventListener("keydown", onKey);
        return () => document.removeEventListener("keydown", onKey);
    }, [onClose]);

    const datePart = (s: string) => s?.split("T")[0] ?? "";
    const timePart = (s: string) => s?.split("T")[1]?.slice(0, 5) ?? "";

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route("tenant.agendamentos.update", agendamento.id), {
            onSuccess: onClose,
        });
    };

    return (
        <div
            className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 backdrop-blur-sm sm:items-center px-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-editar-reserva"
        >
            <div
                className="w-full max-w-md rounded-2xl p-7 shadow-2xl"
                style={{
                    background: "var(--bg-surface)",
                    border: "1px solid var(--border-strong)",
                }}
            >
                <div className="mb-5 flex items-start justify-between">
                    <h3
                        id="modal-editar-reserva"
                        style={{
                            fontFamily: "Instrument Serif, Georgia, serif",
                        }}
                        className="text-xl font-semibold text-primary"
                    >
                        Editar agendamento
                    </h3>
                    <button
                        onClick={onClose}
                        style={{ color: "var(--text-3)" }}
                        className="hover:text-primary transition-colors"
                        aria-label="Fechar"
                    >
                        ✕
                    </button>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="label mb-1">Serviço / Recurso</label>
                        <select
                            value={data.recurso_id}
                            onChange={(e) =>
                                setData("recurso_id", Number(e.target.value))
                            }
                            className="input"
                        >
                            <option value="">Sem recurso</option>
                            {recursos.map((r) => (
                                <option key={r.id} value={r.id}>
                                    {r.nome}
                                </option>
                            ))}
                        </select>
                        {errors.recurso_id && (
                            <p className="mt-1 text-xs text-red-400">
                                {errors.recurso_id}
                            </p>
                        )}
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="label mb-1">
                                Nome do cliente
                            </label>
                            <input
                                value={data.cliente_nome}
                                onChange={(e) =>
                                    setData("cliente_nome", e.target.value)
                                }
                                className="input"
                                required
                            />
                            {errors.cliente_nome && (
                                <p className="mt-1 text-xs text-red-400">
                                    {errors.cliente_nome}
                                </p>
                            )}
                        </div>
                        <div>
                            <label className="label mb-1">Telefone</label>
                            <input
                                value={data.cliente_telefone}
                                onChange={(e) =>
                                    setData("cliente_telefone", e.target.value)
                                }
                                className="input"
                                required
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-3 gap-3">
                        <div>
                            <label className="label mb-1">Data</label>
                            <input
                                type="date"
                                value={datePart(data.inicio)}
                                onChange={(e) => {
                                    const d = e.target.value;
                                    setData(
                                        "inicio",
                                        `${d}T${timePart(data.inicio) || "09:00"}`,
                                    );
                                }}
                                className="input"
                                required
                            />
                        </div>
                        <div>
                            <label className="label mb-1">Início</label>
                            <input
                                type="time"
                                value={timePart(data.inicio)}
                                onChange={(e) =>
                                    setData(
                                        "inicio",
                                        `${datePart(data.inicio)}T${e.target.value}`,
                                    )
                                }
                                className="input"
                                required
                            />
                            {errors.inicio && (
                                <p className="mt-1 text-xs text-red-400">
                                    {errors.inicio}
                                </p>
                            )}
                        </div>
                        <div>
                            <label className="label mb-1">Fim</label>
                            <input
                                type="time"
                                value={timePart(data.fim)}
                                onChange={(e) =>
                                    setData(
                                        "fim",
                                        `${datePart(data.inicio)}T${e.target.value}`,
                                    )
                                }
                                className="input"
                                required
                            />
                            {errors.fim && (
                                <p className="mt-1 text-xs text-red-400">
                                    {errors.fim}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="label mb-1">Status</label>
                            <select
                                value={data.status}
                                onChange={(e) =>
                                    setData("status", e.target.value as Agendamento["status"])
                                }
                                className="input"
                            >
                                <option value="confirmado">Confirmado</option>
                                <option value="concluido">Concluído</option>
                                <option value="cancelado">Cancelado</option>
                            </select>
                        </div>
                        <div>
                            <label className="label mb-1">Observações</label>
                            <textarea
                                value={data.observacoes}
                                onChange={(e) =>
                                    setData("observacoes", e.target.value)
                                }
                                rows={2}
                                className="input"
                            />
                        </div>
                    </div>

                    <div className="flex justify-end gap-2 pt-1">
                        <button
                            type="button"
                            onClick={onClose}
                            className="btn-secondary"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="btn-primary"
                        >
                            {processing ? "Salvando…" : "Salvar alterações"}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ─── Modal de nova reserva ────────────────────────────────────────────────────

function NovaReservaModal({
    recursos,
    onClose,
}: {
    recursos: Recurso[];
    onClose: () => void;
}) {
    const { data, setData, post, processing, errors } = useForm({
        recurso_id: recursos[0]?.id ?? ("" as number | string),
        cliente_nome: "",
        cliente_telefone: "",
        inicio: "",
        fim: "",
        observacoes: "",
        notificar_cliente: false as boolean,
    });

    const [slots, setSlots] = useState<string[]>([]);
    const clienteNomeRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        clienteNomeRef.current?.focus();
        const onKey = (e: KeyboardEvent) => {
            if (e.key === "Escape") onClose();
        };
        document.addEventListener("keydown", onKey);
        return () => document.removeEventListener("keydown", onKey);
    }, [onClose]);

    const buscarSlots = async (recursoId: number | string, data: string) => {
        if (!recursoId || !data) return;
        try {
            const res = await fetch(
                route("tenant.agenda.disponibilidade") +
                    `?recurso_id=${recursoId}&data_inicio=${data}&data_fim=${data}`,
                { headers: { "X-Requested-With": "XMLHttpRequest" } },
            );
            const ags: { start: string }[] = await res.json();
            const ocupadas = new Set(
                ags.map((a) => new Date(a.start).getHours()),
            );
            setSlots(
                Array.from({ length: 15 }, (_, i) => i + 7)
                    .filter((h) => !ocupadas.has(h))
                    .map((h) => `${String(h).padStart(2, "0")}:00`),
            );
        } catch {
            /* ignore */
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route("tenant.agendamentos.store"), { onSuccess: onClose });
    };

    const datePart = (iso: string) => iso?.split("T")[0] ?? "";
    const timePart = (iso: string) => iso?.split("T")[1]?.slice(0, 5) ?? "";

    return (
        <div
            className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 backdrop-blur-sm sm:items-center px-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-nova-reserva"
        >
            <div
                className="w-full max-w-md rounded-2xl p-7 shadow-2xl"
                style={{
                    background: "var(--bg-surface)",
                    border: "1px solid var(--border-strong)",
                }}
            >
                <div className="mb-5 flex items-start justify-between">
                    <h3
                        id="modal-nova-reserva"
                        style={{
                            fontFamily: "Instrument Serif, Georgia, serif",
                        }}
                        className="text-xl font-semibold text-primary"
                    >
                        Nova reserva manual
                    </h3>
                    <button
                        onClick={onClose}
                        style={{ color: "var(--text-3)" }}
                        className="hover:text-primary transition-colors"
                        aria-label="Fechar"
                    >
                        ✕
                    </button>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label
                            className="label mb-1"
                            htmlFor="nova-reserva-recurso"
                        >
                            Recurso
                        </label>
                        <select
                            id="nova-reserva-recurso"
                            value={data.recurso_id}
                            onChange={(e) => {
                                setData("recurso_id", Number(e.target.value));
                                buscarSlots(
                                    e.target.value,
                                    datePart(data.inicio),
                                );
                            }}
                            className="input"
                            required
                        >
                            {recursos.map((r) => (
                                <option key={r.id} value={r.id}>
                                    {r.nome}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label
                                className="label mb-1"
                                htmlFor="nova-reserva-nome"
                            >
                                Nome do cliente
                            </label>
                            <input
                                id="nova-reserva-nome"
                                ref={clienteNomeRef}
                                value={data.cliente_nome}
                                onChange={(e) =>
                                    setData("cliente_nome", e.target.value)
                                }
                                className="input"
                                placeholder="João Silva"
                                required
                            />
                            {errors.cliente_nome && (
                                <p className="mt-1 text-xs text-red-400">
                                    {errors.cliente_nome}
                                </p>
                            )}
                        </div>
                        <div>
                            <label
                                className="label mb-1"
                                htmlFor="nova-reserva-telefone"
                            >
                                Telefone
                            </label>
                            <input
                                id="nova-reserva-telefone"
                                value={data.cliente_telefone}
                                onChange={(e) =>
                                    setData("cliente_telefone", e.target.value)
                                }
                                className="input"
                                placeholder="55119…"
                                required
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-3 gap-3">
                        <div className="col-span-1">
                            <label className="label mb-1">Data</label>
                            <input
                                type="date"
                                value={datePart(data.inicio)}
                                onChange={(e) => {
                                    const hora =
                                        timePart(data.inicio) || "09:00";
                                    setData(
                                        "inicio",
                                        `${e.target.value}T${hora}`,
                                    );
                                    buscarSlots(
                                        data.recurso_id,
                                        e.target.value,
                                    );
                                }}
                                className="input"
                                required
                            />
                        </div>
                        <div>
                            <label className="label mb-1">Início</label>
                            {slots.length > 0 ? (
                                <select
                                    value={timePart(data.inicio)}
                                    onChange={(e) =>
                                        setData(
                                            "inicio",
                                            `${datePart(data.inicio)}T${e.target.value}`,
                                        )
                                    }
                                    className="input"
                                >
                                    <option value="">Selecione</option>
                                    {slots.map((s) => (
                                        <option key={s} value={s}>
                                            {s}
                                        </option>
                                    ))}
                                </select>
                            ) : (
                                <input
                                    type="time"
                                    value={timePart(data.inicio)}
                                    onChange={(e) =>
                                        setData(
                                            "inicio",
                                            `${datePart(data.inicio)}T${e.target.value}`,
                                        )
                                    }
                                    className="input"
                                />
                            )}
                        </div>
                        <div>
                            <label className="label mb-1">Fim</label>
                            <input
                                type="time"
                                value={timePart(data.fim)}
                                onChange={(e) =>
                                    setData(
                                        "fim",
                                        `${datePart(data.inicio)}T${e.target.value}`,
                                    )
                                }
                                className="input"
                                required
                            />
                        </div>
                    </div>

                    <div>
                        <label className="label mb-1">Observações</label>
                        <textarea
                            value={data.observacoes}
                            onChange={(e) =>
                                setData("observacoes", e.target.value)
                            }
                            rows={2}
                            className="input"
                        />
                    </div>

                    <label
                        className="flex items-center gap-2 text-sm"
                        style={{ color: "var(--text-2)" }}
                    >
                        <input
                            type="checkbox"
                            checked={data.notificar_cliente}
                            onChange={(e) =>
                                setData("notificar_cliente", e.target.checked)
                            }
                            className="rounded"
                        />
                        Notificar cliente via WhatsApp
                    </label>

                    <div className="flex justify-end gap-2 pt-1">
                        <button
                            type="button"
                            onClick={onClose}
                            className="btn-secondary"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="btn-primary"
                        >
                            {processing ? "Salvando…" : "Confirmar reserva"}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ─── Main component ───────────────────────────────────────────────────────────

export default function AgendamentosIndex({
    agendamentos,
    recursos,
    filtros,
}: Props) {
    const [modalAberto, setModalAberto] = useState(false);
    const [agendamentoEditando, setAgendamentoEditando] =
        useState<Agendamento | null>(null);
    const { confirm, modal: confirmModal } = useConfirm();

    const filtrar = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const fd = new FormData(e.currentTarget);
        const params: Record<string, string> = {};
        fd.forEach((v, k) => {
            if (v) params[k] = String(v);
        });
        router.get(route("tenant.agendamentos.index"), params, {
            preserveState: true,
        });
    };

    const cancelar = async (id: number) => {
        const ok = await confirm({
            title: "Cancelar agendamento",
            message:
                "O agendamento será marcado como cancelado. Deseja continuar?",
            confirmLabel: "Cancelar agendamento",
            variant: "warning",
        });
        if (ok) router.patch(route("tenant.agendamentos.cancelar", id));
    };

    const concluir = async (id: number) => {
        const ok = await confirm({
            title: "Marcar como concluído",
            message: "Confirmar que este atendimento foi realizado?",
            confirmLabel: "Concluir",
            variant: "default",
        });
        if (ok) router.patch(route("tenant.agendamentos.concluir", id));
    };

    const excluir = async (id: number) => {
        const ok = await confirm({
            title: "Excluir agendamento",
            message:
                "Esta ação remove o registro permanentemente e não pode ser desfeita.",
            confirmLabel: "Excluir",
            variant: "danger",
        });
        if (ok) router.delete(route("tenant.agendamentos.destroy", id));
    };

    return (
        <AppLayout
            title="Agendamentos"
            subtitle="Reservas confirmadas, concluídas e canceladas"
        >
            <Head title="Agendamentos" />
            {confirmModal}

            {/* Filtros */}
            <form
                onSubmit={filtrar}
                className="card mb-5 flex flex-wrap items-end gap-3 p-4"
            >
                <div>
                    <label className="label mb-1">Data</label>
                    <input
                        type="date"
                        name="data"
                        defaultValue={filtros.data ?? ""}
                        className="input"
                    />
                </div>
                <div>
                    <label className="label mb-1">Recurso</label>
                    <select
                        name="recurso_id"
                        defaultValue={filtros.recurso_id ?? ""}
                        className="input"
                    >
                        <option value="">Todos</option>
                        {recursos.map((r) => (
                            <option key={r.id} value={r.id}>
                                {r.nome}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="label mb-1">Status</label>
                    <select
                        name="status"
                        defaultValue={filtros.status ?? ""}
                        className="input"
                    >
                        <option value="">Todos</option>
                        <option value="confirmado">Confirmado</option>
                        <option value="concluido">Concluído</option>
                        <option value="cancelado">Cancelado</option>
                    </select>
                </div>
                <div className="flex-1 min-w-[160px]">
                    <label className="label mb-1">Busca</label>
                    <input
                        type="text"
                        name="busca"
                        defaultValue={filtros.busca ?? ""}
                        placeholder="Nome ou telefone"
                        className="input"
                    />
                </div>
                <div className="flex gap-2">
                    <button type="submit" className="btn-primary">
                        Filtrar
                    </button>
                    <button
                        type="button"
                        onClick={() =>
                            router.get(route("tenant.agendamentos.index"))
                        }
                        className="btn-secondary"
                    >
                        Limpar
                    </button>
                </div>
            </form>

            {/* Tabela */}
            <div className="card overflow-hidden">
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
                                    "Cliente",
                                    "Recurso",
                                    "Início",
                                    "Duração",
                                    "Valor",
                                    "Origem",
                                    "Status",
                                    "",
                                ].map((h) => (
                                    <th
                                        key={h}
                                        className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                                        style={{ color: "var(--text-3)" }}
                                    >
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {agendamentos.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="px-4 py-14 text-center"
                                    >
                                        <div className="flex flex-col items-center gap-2">
                                            <svg
                                                width={20}
                                                height={20}
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                strokeWidth="1.5"
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                                style={{
                                                    color: "var(--text-3)",
                                                }}
                                            >
                                                <rect
                                                    x="3"
                                                    y="4"
                                                    width="18"
                                                    height="18"
                                                    rx="2"
                                                    ry="2"
                                                />
                                                <line
                                                    x1="16"
                                                    y1="2"
                                                    x2="16"
                                                    y2="6"
                                                />
                                                <line
                                                    x1="8"
                                                    y1="2"
                                                    x2="8"
                                                    y2="6"
                                                />
                                                <line
                                                    x1="3"
                                                    y1="10"
                                                    x2="21"
                                                    y2="10"
                                                />
                                            </svg>
                                            <p className="text-sm font-medium text-primary">
                                                Nenhum agendamento encontrado
                                            </p>
                                            <p
                                                className="text-xs"
                                                style={{
                                                    color: "var(--text-3)",
                                                }}
                                            >
                                                Ajuste os filtros ou aguarde
                                                novos agendamentos via WhatsApp.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            ) : (
                                agendamentos.data.map((a) => (
                                    <tr
                                        key={a.id}
                                        className="table-row-hover"
                                        style={{
                                            borderBottom:
                                                "1px solid var(--border)",
                                        }}
                                    >
                                        <td className="px-4 py-3">
                                            <p className="font-medium text-primary">
                                                {a.cliente_nome}
                                            </p>
                                            <p
                                                className="text-xs"
                                                style={{
                                                    color: "var(--text-3)",
                                                }}
                                            >
                                                {a.cliente_telefone}
                                            </p>
                                        </td>
                                        <td
                                            className="px-4 py-3"
                                            style={{ color: "var(--text-2)" }}
                                        >
                                            <p>
                                                {a.recurso?.nome ??
                                                    (a as any).profissional
                                                        ?.nome ??
                                                    "—"}
                                            </p>
                                            {(a as any).servico?.nome && (
                                                <p
                                                    className="text-xs"
                                                    style={{
                                                        color: "var(--text-3)",
                                                    }}
                                                >
                                                    {(a as any).servico.nome}
                                                </p>
                                            )}
                                        </td>
                                        <td
                                            className="px-4 py-3 whitespace-nowrap"
                                            style={{ color: "var(--text-2)" }}
                                        >
                                            {fmtDt(
                                                (a as any).data_hora ??
                                                    a.inicio,
                                            )}
                                        </td>
                                        <td
                                            className="px-4 py-3"
                                            style={{ color: "var(--text-3)" }}
                                        >
                                            {a.fim
                                                ? duracaoMin(a.inicio, a.fim)
                                                : ((a as any).duracao_minutos ??
                                                  "—")}{" "}
                                            min
                                        </td>
                                        <td
                                            className="px-4 py-3"
                                            style={{ color: "var(--text-2)" }}
                                        >
                                            {a.valor_total
                                                ? Number(
                                                      a.valor_total,
                                                  ).toLocaleString("pt-BR", {
                                                      style: "currency",
                                                      currency: "BRL",
                                                  })
                                                : "—"}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={`badge ${a.origem === "bot" || a.origem === "whatsapp" ? "badge-green" : "badge-gray"}`}
                                            >
                                                {a.origem === "bot" ||
                                                a.origem === "whatsapp"
                                                    ? "🤖 WhatsApp"
                                                    : "📋 Manual"}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={`badge ${STATUS_BADGE[a.status] ?? "badge-gray"}`}
                                            >
                                                {STATUS_LABEL[a.status] ??
                                                    a.status}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex gap-1">
                                                <button
                                                    onClick={() =>
                                                        setAgendamentoEditando(a)
                                                    }
                                                    className="rounded-md px-2.5 py-1 text-xs font-medium transition-colors hover:brightness-125"
                                                    style={{
                                                        background:
                                                            "rgba(99,102,241,0.08)",
                                                        color: "var(--accent)",
                                                        border: "1px solid rgba(99,102,241,0.25)",
                                                    }}
                                                >
                                                    Editar
                                                </button>
                                                {(a.status === "confirmado" ||
                                                    a.status === "agendado") && (
                                                    <>
                                                        <button
                                                            onClick={() =>
                                                                concluir(a.id)
                                                            }
                                                            className="rounded-md px-2.5 py-1 text-xs font-medium transition-colors hover:brightness-125"
                                                            style={{
                                                                background:
                                                                    "rgba(255,255,255,0.06)",
                                                                color: "var(--text-2)",
                                                                border: "1px solid var(--border)",
                                                            }}
                                                        >
                                                            Concluir
                                                        </button>
                                                        <button
                                                            onClick={() =>
                                                                cancelar(a.id)
                                                            }
                                                            className="rounded-md px-2.5 py-1 text-xs font-medium transition-colors"
                                                            style={{
                                                                background:
                                                                    "rgba(239,68,68,0.08)",
                                                                color: "#f87171",
                                                                border: "1px solid rgba(239,68,68,0.2)",
                                                            }}
                                                        >
                                                            Cancelar
                                                        </button>
                                                    </>
                                                )}
                                                <button
                                                    onClick={() =>
                                                        excluir(a.id)
                                                    }
                                                    className="rounded-md px-2.5 py-1 text-xs font-medium transition-colors"
                                                    style={{
                                                        background:
                                                            "rgba(239,68,68,0.04)",
                                                        color: "#f87171",
                                                        border: "1px solid rgba(239,68,68,0.15)",
                                                    }}
                                                    title="Excluir permanentemente"
                                                >
                                                    🗑
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Paginação */}
                {agendamentos.last_page > 1 && (
                    <div
                        className="flex gap-1 px-4 py-3"
                        style={{ borderTop: "1px solid var(--border)" }}
                    >
                        {agendamentos.links.map((link, i) => (
                            <button
                                key={i}
                                disabled={!link.url}
                                onClick={() =>
                                    link.url &&
                                    router.get(
                                        link.url,
                                        {},
                                        { preserveState: true },
                                    )
                                }
                                className="rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors disabled:opacity-40"
                                style={
                                    link.active
                                        ? {
                                              background: "var(--accent)",
                                              borderColor: "var(--accent)",
                                              color: "white",
                                          }
                                        : {
                                              borderColor:
                                                  "var(--border-strong)",
                                              color: "var(--text-2)",
                                          }
                                }
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>

            {/* FAB */}
            <button
                onClick={() => setModalAberto(true)}
                className="fixed bottom-7 right-7 flex items-center gap-2 rounded-full px-5 py-3 text-sm font-semibold text-white shadow-lg transition-all hover:scale-105 hover:brightness-110"
                style={{
                    background: "var(--accent)",
                    boxShadow: "0 4px 20px rgba(99,102,241,0.4)",
                }}
            >
                + Nova reserva
            </button>

            {modalAberto && (
                <NovaReservaModal
                    recursos={recursos}
                    onClose={() => setModalAberto(false)}
                />
            )}
            {agendamentoEditando && (
                <EditarReservaModal
                    agendamento={agendamentoEditando}
                    recursos={recursos}
                    onClose={() => setAgendamentoEditando(null)}
                />
            )}
        </AppLayout>
    );
}
