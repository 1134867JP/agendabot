<?php

namespace Tests\Feature;

use App\Http\Controllers\Tenant\ClienteController;
use App\Models\Cliente;
use App\Models\Conversa;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ClienteAnonimizacaoDiretaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->tenant = Tenant::create([
            'nome' => 'Clínica Diagnóstico',
            'slug' => 'clinica-diagnostico-http',
            'tipo_servico' => 'clinica',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $this->tenant->users()->attach($this->user->id, ['papel' => 'admin']);
        app()->instance('tenant', $this->tenant);

        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Cliente Teste',
            'telefone' => '5554999999999',
        ]);
        $conversa = Conversa::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'telefone_cliente' => $this->cliente->telefone,
            'status_v2' => 'ativa',
        ]);
        $conversa->registrarMensagem('cliente', 'Mensagem preservada');
    }

    private function usuarioAutenticado(): static
    {
        return $this->actingAs($this->user)->withSession(['tenant_id' => $this->tenant->id]);
    }

    public function test_delete_simples_funciona(): void
    {
        Route::delete('/__diag/delete-simple', fn () => response()->noContent())->middleware([]);
        fwrite(STDERR, "[diag] delete simples antes\n");
        $this->usuarioAutenticado()->delete('/__diag/delete-simple')->assertNoContent();
        fwrite(STDERR, "[diag] delete simples depois\n");
    }

    public function test_model_binding_funciona(): void
    {
        Route::delete('/__diag/binding/{cliente}', function (Cliente $cliente) {
            return response()->json(['id' => $cliente->id]);
        })->middleware([]);

        fwrite(STDERR, "[diag] binding antes\n");
        $this->usuarioAutenticado()
            ->delete("/__diag/binding/{$this->cliente->id}")
            ->assertOk()
            ->assertJsonPath('id', $this->cliente->id);
        fwrite(STDERR, "[diag] binding depois\n");
    }

    public function test_controller_pela_rota_com_no_content(): void
    {
        Route::delete('/__diag/controller/{cliente}', function (Cliente $cliente) {
            app(ClienteController::class)->destroy($cliente);

            return response()->noContent();
        })->middleware([]);

        fwrite(STDERR, "[diag] controller antes\n");
        $this->usuarioAutenticado()->delete("/__diag/controller/{$this->cliente->id}")->assertNoContent();
        fwrite(STDERR, "[diag] controller depois\n");
    }

    public function test_redirect_com_flash_funciona(): void
    {
        Route::delete('/__diag/redirect', fn () => redirect('/')->with('success', 'ok'))->middleware([]);

        fwrite(STDERR, "[diag] redirect antes\n");
        $this->usuarioAutenticado()->delete('/__diag/redirect')->assertRedirect('/');
        fwrite(STDERR, "[diag] redirect depois\n");
    }
}
