import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import AgendamentosTable from '@/Components/AgendamentosTable';
import { Agendamento, PageProps, PaginatedData, Tenant } from '@/types';

interface Props extends PageProps {
    tenant: Tenant;
    agendamentos: PaginatedData<Agendamento>;
    filtros: { data?: string; status?: string };
}

export default function AgendamentosIndex({ auth, tenant, agendamentos, filtros }: Props) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Agendamentos</h2>}>
            <Head title="Agendamentos" />

            <div className="py-12">
                <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <AgendamentosTable agendamentos={agendamentos} filtros={filtros} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
