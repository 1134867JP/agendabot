import { Head, router, useForm } from "@inertiajs/react";
import { useState } from "react";
import ConfiguracoesLayout from "@/Layouts/ConfiguracoesLayout";
import EmptyState from "@/Components/UI/EmptyState";
import StatusBadge from "@/Components/UI/StatusBadge";
import Toolbar from "@/Components/UI/Toolbar";
import { PageProps } from "@/types";

type TipoOpcao = "convenio" | "pagamento" | "outro";

interface OpcaoExtra {
    id: number;
    tipo: TipoOpcao;
    nome: string;
    ativo: boolean;
}

interface Props extends PageProps {
    opcoes: OpcaoExtra[];
}

const TIPO_LABEL: Record<TipoOpcao, string> = {
    convenio: "Convênio",
    pagamento: "Forma de pagamento",
    outro: "Outra opção",
};

function OpcaoForm({
    opcao,
    onClose,
}: {
    opcao?: OpcaoExtra;
    onClose: () => void;
}) {
    const form = useForm({
        tipo: opcao?.tipo ?? ("pagamento" as TipoOpcao),
        nome: opcao?.nome ?? "",
        ativo: opcao?.ativo ?? true,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: onClose,
        };

        if (opcao) {
            form.patch(route("tenant.opcoes-extras.update", opcao.id), options);
        } else {
            form.post(route("tenant.opcoes-extras.store"), options);
        }
    };

    return (
        <form
            onSubmit={submit}
            className="card space-y-4 p-4 sm:p-6"
            style={{ borderColor: "var(--accent)" }}
        >
            <h2 className="font-semibold text-primary">
                {opcao ? "Editar opção" : "Nova opção"}
            </h2>

            <div className="grid gap-4 sm:grid-cols-2">
                <div>
                    <label className="label mb-1" htmlFor="opcao-extra-tipo">
                        Tipo *
                    </label>
                    <select
                        id="opcao-extra-tipo"
                        value={form.data.tipo}
                        onChange={(event) =>
                            form.setData("tipo", event.target.value as TipoOpcao)
                        }
                        className="input"
                        required
                    >
                        {Object.entries(TIPO_LABEL).map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </select>
                    {form.errors.tipo && (
                        <p className="mt-1 text-xs text-red-400">
                            {form.errors.tipo}
                        </p>
                    )}
                </div>

                <div>
                    <label className="label mb-1" htmlFor="opcao-extra-nome">
                        Nome *
                    </label>
                    <input
                        id="opcao-extra-nome"
                        value={form.data.nome}
                        onChange={(event) =>
                            form.setData("nome", event.target.value)
                        }
                        className="input"
                        maxLength={255}
                        autoFocus
                        required
                    />
                    {form.errors.nome && (
                        <p className="mt-1 text-xs text-red-400">
                            {form.errors.nome}
                        </p>
                    )}
                </div>
            </div>

            {opcao && (
                <label className="flex items-center gap-2 text-sm text-secondary">
                    <input
                        type="checkbox"
                        checked={form.data.ativo}
                        onChange={(event) =>
                            form.setData("ativo", event.target.checked)
                        }
                    />
                    Opção ativa
                </label>
            )}

            <div className="flex flex-col-reverse gap-2 sm:flex-row">
                <button
                    type="button"
                    onClick={onClose}
                    className="btn-secondary justify-center"
                >
                    Cancelar
                </button>
                <button
                    type="submit"
                    disabled={form.processing}
                    className="btn-primary justify-center"
                >
                    {form.processing
                        ? "Salvando…"
                        : opcao
                          ? "Salvar alterações"
                          : "Criar opção"}
                </button>
            </div>
        </form>
    );
}

export default function OpcaoExtraIndex({ opcoes }: Props) {
    const [criando, setCriando] = useState(false);
    const [editando, setEditando] = useState<number | null>(null);

    const alternarAtivo = (opcao: OpcaoExtra) => {
        router.patch(
            route("tenant.opcoes-extras.update", opcao.id),
            {
                tipo: opcao.tipo,
                nome: opcao.nome,
                ativo: !opcao.ativo,
            },
            { preserveScroll: true },
        );
    };

    return (
        <ConfiguracoesLayout
            title="Opções extras"
            subtitle="Convênios, formas de pagamento e outras informações do atendimento"
        >
            <Head title="Opções extras" />

            <Toolbar className="mb-5">
                <p className="text-sm text-muted">
                    {opcoes.length} {opcoes.length === 1 ? "opção cadastrada" : "opções cadastradas"}
                </p>
                {!criando && (
                    <button
                        type="button"
                        onClick={() => {
                            setCriando(true);
                            setEditando(null);
                        }}
                        className="btn-primary"
                    >
                        + Nova opção
                    </button>
                )}
            </Toolbar>

            <div className="space-y-3">
                {criando && <OpcaoForm onClose={() => setCriando(false)} />}

                {opcoes.length === 0 && !criando && (
                    <div className="card overflow-hidden">
                        <EmptyState
                            title="Nenhuma opção cadastrada"
                            description="Cadastre formas de pagamento, convênios ou outras opções que o bot pode informar."
                            action={
                                <button
                                    type="button"
                                    onClick={() => setCriando(true)}
                                    className="btn-primary min-h-11"
                                >
                                    Criar opção
                                </button>
                            }
                        />
                    </div>
                )}

                {opcoes.map((opcao) =>
                    editando === opcao.id ? (
                        <OpcaoForm
                            key={opcao.id}
                            opcao={opcao}
                            onClose={() => setEditando(null)}
                        />
                    ) : (
                        <div
                            key={opcao.id}
                            className="card flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5"
                        >
                            <div className="min-w-0">
                                <p className="font-semibold text-primary">
                                    {opcao.nome}
                                </p>
                                <p className="mt-1 text-xs text-muted">
                                    {TIPO_LABEL[opcao.tipo]}
                                </p>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <StatusBadge
                                    tone={opcao.ativo ? "success" : "neutral"}
                                    dot
                                >
                                    {opcao.ativo ? "Ativa" : "Inativa"}
                                </StatusBadge>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setEditando(opcao.id);
                                        setCriando(false);
                                    }}
                                    className="btn-secondary min-h-10 text-xs"
                                >
                                    Editar
                                </button>
                                <button
                                    type="button"
                                    onClick={() => alternarAtivo(opcao)}
                                    className="btn-secondary min-h-10 text-xs"
                                >
                                    {opcao.ativo ? "Desativar" : "Ativar"}
                                </button>
                            </div>
                        </div>
                    ),
                )}
            </div>
        </ConfiguracoesLayout>
    );
}
