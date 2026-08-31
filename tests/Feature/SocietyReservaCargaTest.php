<?php

namespace Tests\Feature;

use App\Exceptions\HorarioIndisponivelException;
use App\Models\Agendamento;
use App\Models\Recurso;
use App\Models\Tenant;
use App\Services\AgendamentoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocietyReservaCargaTest extends TestCase
{
    use RefreshDatabase;

    public function test_society_suporta_reservas_em_massa_sem_sobreposicao_entre_quadras(): void
    {
        $tenant = Tenant::create([
            'nome' => 'Society de Carga',
            'slug' => 'society-carga',
            'tipo_servico' => 'quadra',
            'ativo' => true,
            'timezone' => 'America/Sao_Paulo',
            'configuracoes' => ['regras_agendamento' => ['buffer_entre_agendamentos_minutos' => 0]],
        ]);
        $quadras = collect(range(1, 4))->map(fn (int $numero) => Recurso::create([
            'tenant_id' => $tenant->id,
            'nome' => "Quadra {$numero}",
            'duracao_padrao_minutos' => 60,
            'ativo' => true,
        ]));
        $service = app(AgendamentoService::class);
        $inicioBase = Carbon::tomorrow('America/Sao_Paulo')->setTime(8, 0);

        foreach ($quadras as $indiceQuadra => $quadra) {
            foreach (range(0, 9) as $slot) {
                $inicio = $inicioBase->copy()->addHours($slot);
                $service->criar($tenant, $this->dados($quadra, $inicio, "Cliente {$indiceQuadra}-{$slot}"));
            }
        }

        $this->assertSame(40, Agendamento::where('tenant_id', $tenant->id)->count());

        foreach ($quadras as $quadra) {
            try {
                $service->criar($tenant, $this->dados($quadra, $inicioBase, 'Tentativa duplicada'));
                $this->fail("A {$quadra->nome} aceitou duas reservas no mesmo horário.");
            } catch (HorarioIndisponivelException) {
                $this->addToAssertionCount(1);
            }
        }

        $cancelado = Agendamento::where('tenant_id', $tenant->id)->where('recurso_id', $quadras->first()->id)->firstOrFail();
        $service->cancelar($cancelado);
        $novo = $service->criar($tenant, $this->dados($quadras->first(), Carbon::parse($cancelado->inicio), 'Cliente após cancelamento'));

        $this->assertSame('confirmado', $novo->status);
        $this->assertSame(40, Agendamento::where('tenant_id', $tenant->id)->where('status', '!=', 'cancelado')->count());
    }

    private function dados(Recurso $quadra, Carbon $inicio, string $cliente): array
    {
        return [
            'recurso_id' => $quadra->id,
            'cliente_nome' => $cliente,
            'cliente_telefone' => '555'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addHour(),
            'status' => 'confirmado',
            'origem' => 'teste_carga',
        ];
    }
}
