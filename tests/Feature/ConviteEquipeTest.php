<?php

namespace Tests\Feature;

use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ConviteEquipe;
use App\Notifications\RedefinirSenha;
use App\Notifications\VerificarEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConviteEquipeTest extends TestCase
{
    use RefreshDatabase;

    private function tenantComAdmin(): array
    {
        $admin = User::factory()->create(['name' => 'Seu João']);
        $tenant = Tenant::create([
            'nome' => 'Barbearia do João',
            'slug' => 'barbearia-convite',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $tenant->users()->attach($admin->id, ['papel' => 'admin', 'ativo' => true]);

        return [$tenant, $admin];
    }

    private function tokenDoConvite(User $user): string
    {
        $token = null;
        Notification::assertSentTo($user, ConviteEquipe::class, function (ConviteEquipe $n) use (&$token) {
            $token = $n->token;

            return true;
        });

        return $token;
    }

    public function test_barbeiro_convidado_cria_a_propria_senha_e_entra(): void
    {
        Notification::fake();
        [$tenant, $admin] = $this->tenantComAdmin();
        $profissional = Profissional::create(['tenant_id' => $tenant->id, 'nome' => 'Pedro', 'ativo' => true]);

        $this->actingAs($admin)->withSession(['tenant_id' => $tenant->id])
            ->post(route('tenant.equipe.store'), [
                'name' => 'Pedro Barbeiro',
                'email' => 'pedro@barbearia.test',
                'papel' => 'profissional',
                'profissional_id' => $profissional->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $pedro = User::where('email', 'pedro@barbearia.test')->firstOrFail();
        $this->assertNull($pedro->email_verified_at);
        $this->assertSame($pedro->id, $profissional->fresh()->user_id);
        Notification::assertNotSentTo($pedro, VerificarEmail::class);

        $token = $this->tokenDoConvite($pedro);
        $mail = (new ConviteEquipe($token, 'Barbearia do João', 'Seu João'))->toMail($pedro);
        $this->assertStringContainsString('convite=1', $mail->actionUrl);
        $this->assertStringContainsString('Seu João convidou você', implode(' ', $mail->introLines));

        auth()->logout();
        $this->get($mail->actionUrl)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/ResetPassword')->where('convite', true));

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'pedro@barbearia.test',
            'password' => 'SenhaDoPedro#2026',
            'password_confirmation' => 'SenhaDoPedro#2026',
            'convite' => true,
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        $pedro->refresh();
        $this->assertTrue(Hash::check('SenhaDoPedro#2026', $pedro->password));
        $this->assertNotNull($pedro->email_verified_at, 'Aceitar o convite comprova o e-mail.');

        $this->post('/login', ['email' => 'pedro@barbearia.test', 'password' => 'SenhaDoPedro#2026']);
        $this->assertAuthenticatedAs($pedro);

        // O mesmo convite não pode ser reutilizado.
        auth()->logout();
        $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'pedro@barbearia.test',
            'password' => 'OutraSenha#2026',
            'password_confirmation' => 'OutraSenha#2026',
            'convite' => true,
        ])->assertSessionHasErrors('email');
    }

    public function test_convite_vale_sete_dias_e_expira_depois(): void
    {
        Notification::fake();
        [$tenant, $admin] = $this->tenantComAdmin();

        $this->actingAs($admin)->withSession(['tenant_id' => $tenant->id])
            ->post(route('tenant.equipe.store'), [
                'name' => 'Ana', 'email' => 'ana@barbearia.test', 'papel' => 'recepcionista',
            ])->assertSessionHasNoErrors();

        $ana = User::where('email', 'ana@barbearia.test')->firstOrFail();
        $token = $this->tokenDoConvite($ana);
        auth()->logout();

        $this->travel(8)->days();

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'ana@barbearia.test',
            'password' => 'SenhaDaAna#2026',
            'password_confirmation' => 'SenhaDaAna#2026',
            'convite' => true,
        ])->assertSessionHasErrors(['email' => 'Este convite expirou ou já foi usado. Peça um novo convite a quem cadastrou você.']);
    }

    public function test_link_de_esqueci_a_senha_nao_vira_convite_de_sete_dias(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'joao@barbearia.test']);

        $this->post('/forgot-password', ['email' => 'joao@barbearia.test']);
        $token = null;
        Notification::assertSentTo($user, RedefinirSenha::class, function (RedefinirSenha $n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->travel(2)->hours();

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'joao@barbearia.test',
            'password' => 'NovaSenha#2026',
            'password_confirmation' => 'NovaSenha#2026',
            'convite' => true,
        ])->assertSessionHasErrors('email');
    }

    public function test_admin_reenvia_convite_e_o_link_anterior_deixa_de_valer(): void
    {
        Notification::fake();
        [$tenant, $admin] = $this->tenantComAdmin();

        $this->actingAs($admin)->withSession(['tenant_id' => $tenant->id])
            ->post(route('tenant.equipe.store'), [
                'name' => 'Lucas', 'email' => 'lucas@barbearia.test', 'papel' => 'recepcionista',
            ]);
        $lucas = User::where('email', 'lucas@barbearia.test')->firstOrFail();
        $primeiro = $this->tokenDoConvite($lucas);

        $this->actingAs($admin)->withSession(['tenant_id' => $tenant->id])
            ->get(route('tenant.equipe.index'))
            ->assertInertia(fn ($page) => $page->where('usuarios.1.convite_pendente', true));

        $this->actingAs($admin)->withSession(['tenant_id' => $tenant->id])
            ->post(route('tenant.equipe.reenviar-convite', $lucas))
            ->assertRedirect();

        Notification::assertSentToTimes($lucas, ConviteEquipe::class, 2);
        auth()->logout();

        $this->post(route('password.store'), [
            'token' => $primeiro,
            'email' => 'lucas@barbearia.test',
            'password' => 'SenhaDoLucas#2026',
            'password_confirmation' => 'SenhaDoLucas#2026',
            'convite' => true,
        ])->assertSessionHasErrors('email');
    }

    public function test_nao_reenvia_convite_para_membro_de_outro_estabelecimento(): void
    {
        Notification::fake();
        [$tenant, $admin] = $this->tenantComAdmin();
        $estranho = User::factory()->unverified()->create();

        $this->actingAs($admin)->withSession(['tenant_id' => $tenant->id])
            ->post(route('tenant.equipe.reenviar-convite', $estranho))
            ->assertForbidden();

        Notification::assertNothingSentTo($estranho);
    }

    public function test_senha_provisoria_continua_funcionando(): void
    {
        Notification::fake();
        [$tenant, $admin] = $this->tenantComAdmin();
        $senhaProvisoria = Str::password(16);

        $this->actingAs($admin)->withSession(['tenant_id' => $tenant->id])
            ->post(route('tenant.equipe.store'), [
                'name' => 'Maria', 'email' => 'maria@barbearia.test', 'papel' => 'recepcionista',
                'password' => $senhaProvisoria,
            ])->assertSessionHasNoErrors();

        $maria = User::where('email', 'maria@barbearia.test')->firstOrFail();
        $this->assertTrue(Hash::check($senhaProvisoria, $maria->password));
        Notification::assertNotSentTo($maria, ConviteEquipe::class);
        Notification::assertSentTo($maria, VerificarEmail::class);
    }

    public function test_superadmin_cria_estabelecimento_e_convida_o_dono(): void
    {
        Notification::fake();
        $superAdmin = User::factory()->create();
        $superAdmin->forceFill(['is_super_admin' => true])->save();

        $this->actingAs($superAdmin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('superadmin.tenants.store'), [
                'nome' => 'Barbearia Nova',
                'tipo_servico' => 'barbeiro',
                'email_dono' => 'dono@nova.test',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('superadmin.tenants.index'));

        $dono = User::where('email', 'dono@nova.test')->firstOrFail();
        $token = $this->tokenDoConvite($dono);
        Notification::assertNotSentTo($dono, VerificarEmail::class);
        auth()->logout();

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'dono@nova.test',
            'password' => 'SenhaDoDono#2026',
            'password_confirmation' => 'SenhaDoDono#2026',
            'convite' => true,
        ])->assertSessionHasNoErrors();

        $this->assertTrue($dono->fresh()->hasVerifiedEmail());
    }
}
