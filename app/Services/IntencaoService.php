<?php

namespace App\Services;

class IntencaoService
{
    /**
     * Detecta confirmação/cancelamento simples quando há agendamento pendente,
     * evitando chamada ao Claude para mensagens triviais.
     * Retorna 'confirmar' | 'cancelar' | null (null = chamar Claude normalmente).
     */
    public function detectarConfirmacao(string $mensagem): ?string
    {
        $msg = mb_strtolower(trim($mensagem), 'UTF-8');

        if (preg_match('/^(sim|s|ok|confirmar?|isso|correto|pode|perfeito|certo|bom|ótimo|ótima|tá|ta|tá bom|ta bom|quero|confirmo)$/u', $msg)) {
            return 'confirmar';
        }

        if (preg_match('/^(n[aã]o?|cancelar?|desistir?|errado|não quero|nao quero|cancela|cancelo|desisto)$/u', $msg)) {
            return 'cancelar';
        }

        return null;
    }
}
