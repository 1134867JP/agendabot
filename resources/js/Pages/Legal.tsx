import { Head, Link } from '@inertiajs/react';
import LandingLayout from '@/Layouts/LandingLayout';

interface Props {
    kind: 'terms' | 'privacy';
    legal: {
        version: string;
        entity_name: string;
        entity_document?: string | null;
        contact_email?: string | null;
    };
}

const terms = [
    ['1. Serviço', 'O Agendou oferece agenda, automação de atendimento, integrações com WhatsApp e recursos de inteligência artificial para estabelecimentos. As funcionalidades disponíveis dependem do plano contratado.'],
    ['2. Conta e responsabilidades', 'Você deve fornecer informações verdadeiras, proteger suas credenciais, manter os dados do estabelecimento atualizados e garantir que possui autorização para tratar os dados inseridos na plataforma.'],
    ['3. Uso do WhatsApp e de IA', 'Você é responsável pelas mensagens enviadas em nome do estabelecimento, pelas instruções configuradas para o bot e pelo cumprimento das regras do WhatsApp. Respostas automatizadas podem conter imprecisões e devem ser supervisionadas em situações sensíveis.'],
    ['4. Uso proibido', 'Não é permitido usar o serviço para fraude, assédio, conteúdo ilegal, discriminação, coleta abusiva de dados, envio de spam ou tentativa de comprometer a segurança da plataforma e de terceiros.'],
    ['5. Planos, trial e pagamentos', 'O trial é limitado a uma conta e um estabelecimento. Valores, limites e ciclos são apresentados antes da contratação. Cancelamentos de cobranças recorrentes somente são considerados concluídos após confirmação do meio de pagamento.'],
    ['6. Disponibilidade e integrações', 'O funcionamento depende de serviços externos, como WhatsApp, Evolution API, provedores de IA, Asaas e Google. Poderão ocorrer indisponibilidades, manutenções e mudanças impostas por esses fornecedores.'],
    ['7. Suspensão e encerramento', 'Contas podem ser suspensas por inadimplência, risco de segurança ou violação destes Termos. Antes de excluir uma conta administradora, é necessário transferir a administração ou encerrar o estabelecimento.'],
    ['8. Alterações', 'Estes Termos podem ser atualizados. Quando a alteração for relevante, uma nova versão e sua data serão disponibilizadas.'],
];

const privacy = [
    ['1. Papéis no tratamento', 'O estabelecimento é controlador dos dados de seus clientes. O Agendou atua principalmente como operador ao processar esses dados conforme as configurações e instruções do estabelecimento, e como controlador dos dados necessários para administrar contas, segurança e cobrança.'],
    ['2. Dados tratados', 'Podemos tratar dados de cadastro e contato, informações do estabelecimento, agenda, clientes, conversas, registros técnicos, uso da plataforma, pagamentos e dados necessários às integrações habilitadas. Evite inserir dados sensíveis que não sejam necessários ao atendimento.'],
    ['3. Finalidades e bases legais', 'Os dados são usados para executar o serviço contratado, autenticar usuários, processar agendamentos, entregar mensagens, cobrar planos, prevenir fraude, manter segurança, cumprir obrigações legais e atender direitos dos titulares. A base legal varia conforme a finalidade, incluindo execução de contrato, cumprimento legal e legítimo interesse.'],
    ['4. Compartilhamento', 'Dados podem ser enviados, no limite necessário, a provedores de hospedagem e banco de dados, WhatsApp e Evolution API, provedores de inteligência artificial configurados, Asaas, Google Calendar, e-mail e ferramentas de observabilidade. Esses fornecedores podem processar dados em outros países.'],
    ['5. Retenção', 'Mensagens são mantidas conforme o plano: 30 dias no Starter, 90 dias no Pro e histórico completo no Business, salvo necessidade legal, solicitação válida de eliminação ou backup operacional protegido. Outros registros são mantidos pelo período necessário ao contrato, à segurança e a obrigações legais.'],
    ['6. Segurança', 'Adotamos controles como isolamento por estabelecimento, criptografia de sessões e backups, autenticação de webhooks, limitação de requisições e monitoramento. Nenhum ambiente é totalmente isento de riscos; incidentes relevantes serão tratados conforme a legislação aplicável.'],
    ['7. Direitos do titular', 'O titular pode solicitar confirmação de tratamento, acesso, correção, anonimização, bloqueio, eliminação quando aplicável, informação sobre compartilhamentos e revisão de decisões automatizadas. Pedidos sobre dados de clientes devem ser dirigidos primeiro ao estabelecimento responsável.'],
    ['8. Contato e alterações', 'Solicitações sobre privacidade podem ser enviadas ao contato indicado abaixo. Esta política poderá ser atualizada, preservando a identificação da versão vigente.'],
];

export default function Legal({ kind, legal }: Props) {
    const isTerms = kind === 'terms';
    const title = isTerms ? 'Termos de Uso' : 'Política de Privacidade';
    const sections = isTerms ? terms : privacy;

    return (
        <LandingLayout>
            <Head title={title} />
            <main className="mx-auto max-w-3xl px-5 pb-20 pt-28 sm:px-6 sm:pt-32">
                <Link href={route('home')} className="text-sm" style={{ color: '#6ed7bd' }}>← Voltar ao início</Link>
                <h1 className="mt-6 text-4xl" style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}>{title}</h1>
                <p className="mt-3 text-sm text-white/50">Versão {legal.version} · última atualização em 28 de agosto de 2026</p>

                <div className="mt-10 space-y-8 text-sm leading-7 text-white/70">
                    {sections.map(([heading, body]) => (
                        <section key={heading}>
                            <h2 className="text-lg font-semibold text-white/90">{heading}</h2>
                            <p className="mt-2">{body}</p>
                        </section>
                    ))}

                    <section className="rounded-xl border border-white/10 bg-white/[0.03] p-5">
                        <h2 className="text-lg font-semibold text-white/90">Responsável e contato</h2>
                        <p className="mt-2">{legal.entity_name}{legal.entity_document ? ` · ${legal.entity_document}` : ''}</p>
                        {legal.contact_email
                            ? <a href={`mailto:${legal.contact_email}`} className="mt-1 inline-block underline" style={{ color: '#6ed7bd' }}>{legal.contact_email}</a>
                            : <p className="mt-1 text-amber-300">O canal de privacidade será informado antes da abertura pública.</p>}
                    </section>

                    <p className="text-xs text-white/40">Este documento é uma base operacional e deve ser revisado por assessoria jurídica antes da abertura pública do serviço.</p>
                </div>
            </main>
        </LandingLayout>
    );
}
