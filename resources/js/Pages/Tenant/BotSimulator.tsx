import { Head } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';

type Message = { from: 'user' | 'bot'; text: string };

const replies = [
    'Olá! Sou o assistente do estabelecimento. Posso consultar horários, serviços ou ajudar com um agendamento.',
    'Perfeito. Para continuar, informe o serviço e o dia que você prefere.',
    'Encontrei estes horários de exemplo: 09:00, 10:30 e 14:00. Qual deles você prefere?',
    'Ótimo! Antes de confirmar, vou revisar o serviço, o profissional, a data e o horário com você.',
];

export default function BotSimulator() {
    const [messages, setMessages] = useState<Message[]>([
        { from: 'bot', text: 'Olá! Esta é uma simulação local. Nenhuma mensagem será enviada e nenhuma chamada de IA será feita.' },
    ]);
    const [input, setInput] = useState('');
    const [index, setIndex] = useState(0);

    function send(event: FormEvent) {
        event.preventDefault();
        const text = input.trim();
        if (!text) return;

        setMessages(current => [
            ...current,
            { from: 'user', text },
            { from: 'bot', text: replies[Math.min(index, replies.length - 1)] },
        ]);
        setIndex(current => current + 1);
        setInput('');
    }

    return (
        <AppLayout title="Simulador do bot" subtitle="Valide o roteiro de atendimento antes de conectar o WhatsApp">
            <Head title="Simulador do bot" />

            <div className="mx-auto max-w-2xl">
                <div className="mb-4 rounded-xl p-4" style={{ background: 'var(--jade-light)', border: '1px solid rgba(0,168,132,0.2)' }}>
                    <p className="text-sm font-medium" style={{ color: 'var(--jade)' }}>Ambiente seguro de teste</p>
                    <p className="mt-1 text-xs" style={{ color: 'var(--text-2)' }}>
                        As respostas são simuladas no navegador. O teste não altera agendamentos, não envia WhatsApp e não consome tokens.
                    </p>
                </div>

                <div className="card overflow-hidden">
                    <div className="space-y-3 p-5" style={{ minHeight: 360 }}>
                        {messages.map((message, messageIndex) => (
                            <div key={messageIndex} className={`flex ${message.from === 'user' ? 'justify-end' : 'justify-start'}`}>
                                <div
                                    className="max-w-[85%] rounded-2xl px-4 py-2.5 text-sm"
                                    style={{
                                        background: message.from === 'user' ? 'var(--accent)' : 'var(--bg-surface-2)',
                                        color: message.from === 'user' ? '#fff' : 'var(--text-1)',
                                    }}
                                >
                                    {message.text}
                                </div>
                            </div>
                        ))}
                    </div>

                    <form onSubmit={send} className="flex gap-2 p-4" style={{ borderTop: '1px solid var(--border)' }}>
                        <input
                            value={input}
                            onChange={event => setInput(event.target.value)}
                            placeholder="Ex.: quero agendar um corte"
                            className="min-w-0 flex-1 rounded-lg px-3 py-2 text-sm"
                            style={{ background: 'var(--bg-surface-2)', color: 'var(--text-1)', border: '1px solid var(--border-strong)' }}
                        />
                        <button type="submit" className="btn-primary rounded-lg px-4 py-2 text-sm">Enviar</button>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
