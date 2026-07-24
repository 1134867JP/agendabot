<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Models\Tenant;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class WhatsAppConversationBackupService
{
    private const MAX_BACKUPS = 5;

    private const RETENTION_DAYS = 30;

    public function criarBackup(Tenant $tenant): array
    {
        $diretorio = $this->diretorio($tenant);
        $arquivo = 'whatsapp-'.now()->format('Ymd-His').'.json.enc';
        $caminho = $diretorio.'/'.$arquivo;

        $clientes = Cliente::where('tenant_id', $tenant->id)
            ->orderBy('id')
            ->get([
                'id',
                'nome',
                'telefone',
                'cpf',
                'data_nascimento',
                'observacoes',
                'created_at',
                'updated_at',
            ]);

        $conversas = Conversa::where('tenant_id', $tenant->id)
            ->with(['mensagens' => fn ($query) => $query->orderBy('enviada_em')])
            ->orderBy('id')
            ->get()
            ->map(fn (Conversa $conversa) => [
                'id' => $conversa->id,
                'cliente_id' => $conversa->cliente_id,
                'telefone_cliente' => $conversa->telefone_cliente,
                'status' => $conversa->status_v2,
                'ultima_mensagem_em' => $conversa->ultima_mensagem_em,
                'created_at' => $conversa->created_at,
                'updated_at' => $conversa->updated_at,
                'mensagens' => $conversa->mensagens->map(fn (Mensagem $mensagem) => [
                    'id' => $mensagem->id,
                    'remetente' => $mensagem->remetente,
                    'tipo' => $mensagem->tipo,
                    'conteudo' => $mensagem->conteudo,
                    'evolution_message_id' => $mensagem->evolution_message_id,
                    'enviada_em' => $mensagem->enviada_em,
                ])->values(),
            ])
            ->values();

        $payload = [
            'versao' => 1,
            'criado_em' => now()->toIso8601String(),
            'tenant' => [
                'id' => $tenant->id,
                'nome' => $tenant->nome,
                'slug' => $tenant->slug,
            ],
            'resumo' => [
                'clientes' => $clientes->count(),
                'conversas' => $conversas->count(),
                'mensagens' => $conversas->sum(fn (array $conversa) => $conversa['mensagens']->count()),
            ],
            'clientes' => $clientes,
            'conversas' => $conversas,
        ];

        $json = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        if (! Storage::disk('local')->put($caminho, Crypt::encryptString($json))) {
            throw new RuntimeException('Não foi possível salvar o backup das conversas.');
        }

        $this->removerBackupsAntigos($diretorio);

        return $this->dadosArquivo($tenant, $arquivo);
    }

    public function limparConversas(Tenant $tenant): array
    {
        return DB::transaction(function () use ($tenant): array {
            $conversaIds = Conversa::where('tenant_id', $tenant->id)->pluck('id');
            $mensagens = Mensagem::whereIn('conversa_id', $conversaIds)->count();
            $conversas = $conversaIds->count();

            Mensagem::whereIn('conversa_id', $conversaIds)->delete();
            Conversa::whereIn('id', $conversaIds)->delete();

            return [
                'conversas' => $conversas,
                'mensagens' => $mensagens,
            ];
        });
    }

    public function ultimoBackup(Tenant $tenant): ?array
    {
        $diretorio = $this->diretorio($tenant);
        $arquivos = collect(Storage::disk('local')->files($diretorio))
            ->filter(fn (string $path) => $this->ehBackup($path))
            ->sortDesc();

        $caminho = $arquivos->first();
        if (! $caminho) {
            return null;
        }

        return $this->dadosArquivo($tenant, basename($caminho));
    }

    public function caminho(Tenant $tenant, string $arquivo): string
    {
        abort_unless(
            preg_match('/^whatsapp-\d{8}-\d{6}\.json$/', $arquivo) === 1,
            404,
        );

        $caminho = $this->diretorio($tenant).'/'.$arquivo;
        abort_unless(Storage::disk('local')->exists($caminho), 404);

        return $caminho;
    }

    public function conteudo(Tenant $tenant, string $arquivo): string
    {
        $conteudo = Storage::disk('local')->get($this->caminho($tenant, $arquivo));

        return str_ends_with($arquivo, '.enc')
            ? Crypt::decryptString($conteudo)
            : $conteudo;
    }

    private function dadosArquivo(Tenant $tenant, string $arquivo): array
    {
        $caminho = $this->diretorio($tenant).'/'.$arquivo;

        return [
            'arquivo' => $arquivo,
            'tamanho' => Storage::disk('local')->size($caminho),
            'criado_em' => date(DATE_ATOM, Storage::disk('local')->lastModified($caminho)),
            'url' => route('tenant.whatsapp.backup', ['arquivo' => $arquivo]),
        ];
    }

    private function removerBackupsAntigos(string $diretorio): void
    {
        collect(Storage::disk('local')->files($diretorio))
            ->filter(fn (string $path) => $this->ehBackup($path))
            ->sortDesc()
            ->values()
            ->each(function (string $path, int $indice): void {
                $expirado = Storage::disk('local')->lastModified($path) < now()->subDays(self::RETENTION_DAYS)->timestamp;
                if ($indice >= self::MAX_BACKUPS || $expirado) {
                    Storage::disk('local')->delete($path);
                }
            });
    }

    private function ehBackup(string $path): bool
    {
        return str_ends_with($path, '.json') || str_ends_with($path, '.json.enc');
    }

    private function diretorio(Tenant $tenant): string
    {
        return "whatsapp-backups/tenant-{$tenant->id}";
    }
}
