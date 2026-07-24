import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import AgendamentosTable from '@/Components/AgendamentosTable';
import { Agendamento, PageProps, PaginatedData, Tenant } from '@/types';

interface Props extends PageProps {
    tenant: Tenant;
    agendamentos: PaginatedData<Agendamento>;
    filtros: { data?: string; status?: string };
}

export default function AgendamentosIndex({ agendamentos, filtros }: Props) {
    return (
        <AppLayout
            title="Agendamentos"
            subtitle="Consulte, filtre e acompanhe as reservas do estabelecimento."
        >
            <Head title="Agendamentos" />
            <AgendamentosTable agendamentos={agendamentos} filtros={filtros} />
        </AppLayout>
    );
}
