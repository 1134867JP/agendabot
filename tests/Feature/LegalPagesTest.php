<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_paginas_legais_sao_publicas(): void
    {
        $this->get(route('termos'))->assertOk();
        $this->get(route('privacidade'))->assertOk();
    }

    public function test_cadastro_exige_aceite_e_registra_versao(): void
    {
        $payload = [
            'nome_usuario' => 'Dono Legal',
            'email' => 'legal@example.com',
            'senha' => 'senha12345',
            'senha_confirmation' => 'senha12345',
            'nome_estabelecimento' => 'Empresa Legal',
            'tipo_servico' => 'barbeiro',
            'telefone' => '51999999999',
        ];

        $this->post('/cadastro', $payload)->assertSessionHasErrors('aceite_legal');
        $this->post('/cadastro', [...$payload, 'aceite_legal' => true])->assertRedirect(route('verification.notice'));

        $this->assertDatabaseHas('users', [
            'email' => 'legal@example.com',
            'legal_version' => config('legal.version'),
        ]);
        $user = User::where('email', 'legal@example.com')->firstOrFail();
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertNotNull($user->privacy_accepted_at);
    }
}
