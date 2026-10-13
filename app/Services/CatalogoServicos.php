<?php

namespace App\Services;

use App\Exceptions\HorarioIndisponivelException;
use App\Models\Agendamento;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fonte única de duração e preço de um atendimento. Considera o preço e a
 * duração próprios de cada profissional e soma os serviços de um combo.
 */
class CatalogoServicos
{
    /**
     * @param  array<int|string>  $servicoIds
     * @return array{itens: list<array{servico_id: int, nome: string, valor: ?float, duracao_minutos: int}>, duracao_minutos: int, valor_total: ?float, nomes: string}
     */
    public function montar(Tenant $tenant, array $servicoIds, ?Profissional $profissional = null): array
    {
        $ids = $this->normalizarIds($servicoIds);
        if ($ids === []) {
            return ['itens' => [], 'duracao_minutos' => 0, 'valor_total' => null, 'nomes' => ''];
        }

        $servicos = Servico::where('tenant_id', $tenant->id)->whereIn('id', $ids)->get()->keyBy('id');
        if ($servicos->count() !== count($ids)) {
            throw new \InvalidArgumentException('Serviço não encontrado neste estabelecimento.');
        }

        $personalizados = $this->personalizados($profissional, $ids);

        $itens = [];
        foreach ($ids as $id) {
            $servico = $servicos[$id];
            $pivot = $personalizados->get($id);
            $itens[] = [
                'servico_id' => $servico->id,
                'nome' => $servico->nome,
                'valor' => $pivot?->valor !== null ? (float) $pivot->valor : self::valorFixo($servico),
                'duracao_minutos' => (int) ($pivot?->duracao_minutos ?: $servico->duracao_minutos ?: 30),
            ];
        }

        $valores = array_column($itens, 'valor');

        return [
            'itens' => $itens,
            'duracao_minutos' => array_sum(array_column($itens, 'duracao_minutos')),
            // Uma faixa de preço sem valor definido torna o total desconhecido.
            'valor_total' => in_array(null, $valores, true) ? null : round(array_sum($valores), 2),
            'nomes' => implode(' + ', array_column($itens, 'nome')),
        ];
    }

    /**
     * Garante que o profissional faz todos os serviços pedidos. Profissional
     * sem nenhum serviço vinculado é tratado como "faz tudo" (legado).
     *
     * @param  array<int|string>  $servicoIds
     */
    public function validarProfissionalFaz(Profissional $profissional, array $servicoIds): void
    {
        $faltando = $this->naoAtendidos($profissional, $servicoIds);
        if ($faltando->isEmpty()) {
            return;
        }

        $quemFaz = Profissional::where('tenant_id', $profissional->tenant_id)
            ->where('ativo', true)
            ->whereHas('servicos', fn ($q) => $q->whereIn('servicos.id', $faltando->keys()), '=', $faltando->count())
            ->pluck('nome');

        $mensagem = "{$profissional->nome} não faz {$faltando->join(', ', ' e ')}.";
        if ($quemFaz->isNotEmpty()) {
            $mensagem .= ' Quem faz: '.$quemFaz->join(', ', ' e ').'.';
        }

        throw new HorarioIndisponivelException($mensagem);
    }

    /**
     * @param  array<int|string>  $servicoIds
     * @return Collection<int, string> nomes indexados pelo id do serviço
     */
    public function naoAtendidos(Profissional $profissional, array $servicoIds): Collection
    {
        $ids = $this->normalizarIds($servicoIds);
        $atendidos = $profissional->servicos()->pluck('servicos.id')->map(fn ($id) => (int) $id);
        if ($ids === [] || $atendidos->isEmpty()) {
            return collect();
        }

        return Servico::whereIn('id', array_diff($ids, $atendidos->all()))->pluck('nome', 'id');
    }

    /**
     * Grava os itens do combo no agendamento (substitui os anteriores).
     *
     * @param  array{itens: list<array{servico_id: int, nome: string, valor: ?float, duracao_minutos: int}>}  $montagem
     */
    public function gravarItens(Agendamento $agendamento, array $montagem): void
    {
        $agendamento->itensServico()->delete();
        foreach ($montagem['itens'] as $ordem => $item) {
            $agendamento->itensServico()->create([...$item, 'ordem' => $ordem]);
        }
        $agendamento->unsetRelation('itensServico');
    }

    /** Serviços do agendamento, vindos do combo ou do serviço único legado. */
    public function idsDoAgendamento(Agendamento $agendamento): array
    {
        $ids = $agendamento->itensServico()->whereNotNull('servico_id')->pluck('servico_id')->all();

        return $ids !== [] ? $ids : array_filter([$agendamento->servico_id]);
    }

    public static function valorFixo(Servico $servico): ?float
    {
        if ($servico->valor_min === null) {
            return null;
        }
        if ($servico->valor_max !== null && abs((float) $servico->valor_max - (float) $servico->valor_min) >= 0.01) {
            return null;
        }

        return (float) $servico->valor_min;
    }

    /** @return list<int> */
    public function normalizarIds(array $servicoIds): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $servicoIds))));
    }

    /** @return Collection<int, object> */
    private function personalizados(?Profissional $profissional, array $ids): Collection
    {
        if (! $profissional) {
            return collect();
        }

        return DB::table('profissional_servico')
            ->where('profissional_id', $profissional->id)
            ->whereIn('servico_id', $ids)
            ->get(['servico_id', 'valor', 'duracao_minutos'])
            ->keyBy('servico_id');
    }
}
