<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\RedefinirSenha;
use App\Notifications\VerificarEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RedefinicaoSenhaCompletaTest extends TestCase
{
    use RefreshDatabase;

    public function test_fluxo_completo_do_pedido_ao_login_com_a_nova_senha(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'joao@barbearia.test']);

        $this->post('/forgot-password', ['email' => 'joao@barbearia.test'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', __('passwords.sent'));

        $token = null;
        Notification::assertSentTo($user, RedefinirSenha::class, function (RedefinirSenha $n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'joao@barbearia.test',
            'password' => 'NovaSenha#2026',
            'password_confirmation' => 'NovaSenha#2026',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NovaSenha#2026', $user->fresh()->password));

        // O mesmo link não pode ser reutilizado.
        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'joao@barbearia.test',
            'password' => 'OutraSenha#2026',
            'password_confirmation' => 'OutraSenha#2026',
        ])->assertSessionHasErrors('email');
    }

    public function test_email_sem_conta_recebe_a_mesma_resposta_e_nada_e_enviado(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'ninguem@barbearia.test'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', __('passwords.sent'));

        Notification::assertNothingSent();
    }

    public function test_segundo_pedido_seguido_nao_revela_o_limite_por_usuario(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'joao@barbearia.test']);

        $this->post('/forgot-password', ['email' => 'joao@barbearia.test']);
        $this->post('/forgot-password', ['email' => 'joao@barbearia.test'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', __('passwords.sent'));
    }

    public function test_email_de_redefinicao_em_portugues_vai_pela_fila_com_link_valido(): void
    {
        $user = User::factory()->create(['email' => 'joao@barbearia.test']);
        $notificacao = new RedefinirSenha('token-123');

        $this->assertInstanceOf(ShouldQueue::class, $notificacao);
        $this->assertSame('notifications', $notificacao->queue);

        $mail = $notificacao->toMail($user);
        $this->assertStringStartsWith('Redefinir sua senha', $mail->subject);
        $this->assertSame('Criar nova senha', $mail->actionText);
        $this->assertStringContainsString('/reset-password/token-123', $mail->actionUrl);
        $this->assertStringContainsString('email=joao%40barbearia.test', $mail->actionUrl);

        $html = (string) $mail->render();
        $this->assertStringContainsString('Se o botão "Criar nova senha" não funcionar', $html);
        $this->assertStringContainsString('Todos os direitos reservados', $html);
        $this->assertStringNotContainsString('All rights reserved', $html);
    }

    public function test_confirmacao_de_cadastro_em_portugues_vai_pela_fila(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post(route('verification.send'));

        Notification::assertSentTo($user, VerificarEmail::class, function (VerificarEmail $n) use ($user) {
            $mail = $n->toMail($user);

            return $n->queue === 'notifications'
                && str_starts_with($mail->subject, 'Confirme seu e-mail')
                && str_contains($mail->actionUrl, '/verify-email/');
        });
    }

    public function test_health_ready_acusa_email_nao_configurado_em_producao(): void
    {
        config(['mail.default' => 'log']);
        $this->app->detectEnvironment(fn () => 'production');

        $this->getJson(route('health.ready'))
            ->assertStatus(503)
            ->assertJsonPath('checks.mail', 'not_configured');
    }

    public function test_comando_de_teste_de_email(): void
    {
        config(['mail.default' => 'log']);
        $this->artisan('mail:testar', ['email' => 'dono@barbearia.test'])->assertFailed();

        config(['mail.default' => 'smtp']);
        Mail::fake();
        $this->artisan('mail:testar', ['email' => 'dono@barbearia.test'])->assertSuccessful();
    }
}
