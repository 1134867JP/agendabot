<?php

namespace App\Jobs;

use App\Models\Conversa;
use App\Models\Mensagem;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BackupELimparHistoricoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 120;

    public function __construct(private readonly Tenant $tenant) {}

    public function handle(): void
    {
        $threshold   = now()->subDays(90);
        $conversaIds = Conversa::where('tenant_id', $this->tenant->id)->pluck('id');

        if ($conversaIds->isEmpty()) {
            return;
        }

        $totalMensagens = Mensagem::whereIn('conversa_id', $conversaIds)
            ->where('enviada_em', '<', $threshold)
            ->count();
        if ($totalMensagens === 0) {
            return;
        }

        $this->gerarBackup($conversaIds->all(), $threshold);

        Mensagem::whereIn('conversa_id', $conversaIds)
            ->where('enviada_em', '<', $threshold)
            ->delete();

        // Only reset conversations that no longer have any remaining messages
        $conversasVazias = Conversa::whereIn('id', $conversaIds)
            ->whereDoesntHave('mensagens')
            ->pluck('id');

        if ($conversasVazias->isNotEmpty()) {
            Conversa::whereIn('id', $conversasVazias)->update([
                'ultima_mensagem_em' => null,
                'status_v2'          => 'ativa',
            ]);
        }

        Log::info('BACKUP_E_LIMPAR', [
            'tenant'    => $this->tenant->slug,
            'mensagens' => $totalMensagens,
        ]);
    }

    private function gerarBackup(array $conversaIds, Carbon $threshold): void
    {
        $data     = Carbon::now()->format('Y-m-d_H-i');
        $filename = "backups/{$this->tenant->slug}_{$data}.txt";

        $conversas = Conversa::whereIn('id', $conversaIds)
            ->with(['cliente', 'mensagens' => fn ($q) => $q->where('enviada_em', '<', $threshold)->orderBy('enviada_em')])
            ->orderByDesc('ultima_mensagem_em')
            ->get();

        $linhas   = [];
        $linhas[] = str_repeat('=', 60);
        $linhas[] = "BACKUP DE CONVERSAS — {$this->tenant->nome}";
        $linhas[] = "Gerado em: " . Carbon::now()->format('d/m/Y H:i:s');
        $linhas[] = "Total de conversas: " . $conversas->count();
        $linhas[] = str_repeat('=', 60);

        foreach ($conversas as $conversa) {
            $nomeCli  = $conversa->cliente?->nome ?? $conversa->telefone_cliente;
            $tel      = $conversa->telefone_cliente;

            $linhas[] = '';
            $linhas[] = str_repeat('─', 60);
            $linhas[] = "Conversa: {$nomeCli} ({$tel})";
            $linhas[] = str_repeat('─', 60);

            if ($conversa->mensagens->isEmpty()) {
                $linhas[] = '  (sem mensagens)';
                continue;
            }

            foreach ($conversa->mensagens as $msg) {
                $hora  = Carbon::parse($msg->enviada_em)->format('d/m/Y H:i');
                $quem  = match ($msg->remetente) {
                    'cliente' => $nomeCli,
                    'bot'     => 'Bot',
                    'humano'  => 'Atendente',
                    default   => $msg->remetente,
                };
                $corpo = $msg->tipo !== 'texto'
                    ? "[{$msg->tipo}]" . ($msg->conteudo ? " {$msg->conteudo}" : '')
                    : $msg->conteudo;
                $corpo    = str_replace("\n", "\n        ", $corpo);
                $linhas[] = "[{$hora}] {$quem}: {$corpo}";
            }
        }

        $total    = collect($conversas)->sum(fn ($c) => $c->mensagens->count());
        $linhas[] = '';
        $linhas[] = str_repeat('=', 60);
        $linhas[] = "Fim do backup — {$total} mensagens";

        Storage::put($filename, implode("\n", $linhas));

        Log::info('BACKUP_CRIADO', ['tenant' => $this->tenant->slug, 'arquivo' => $filename]);
    }
}
