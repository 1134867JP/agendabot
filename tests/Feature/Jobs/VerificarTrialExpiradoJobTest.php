<?php

namespace Tests\Feature\Jobs;

use App\Jobs\VerificarTrialExpiradoJob;
use App\Mail\TrialExpiradoMail;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class VerificarTrialExpiradoJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_vencido_vira_expired_e_envia_email(): void
    {
        Mail::fake();

        $admin = User::factory()->create();
        $tenant = Tenant::create([
            'nome' => 'Barbearia Trial Vencido',
            'slug' => 'barbearia-trial-vencido',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->subDay(),
        ]);
        $tenant->users()->attach($admin->id, ['papel' => 'admin']);

        VerificarTrialExpiradoJob::dispatchSync();

        $this->assertSame('expired', $tenant->fresh()->subscription_status);
        Mail::assertQueued(TrialExpiradoMail::class, fn ($mail) => $mail->hasTo($admin->email));
    }

    public function test_trial_ainda_valido_nao_muda(): void
    {
        Mail::fake();

        $admin = User::factory()->create();
        $tenant = Tenant::create([
            'nome' => 'Barbearia Trial Valido',
            'slug' => 'barbearia-trial-valido',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(5),
        ]);
        $tenant->users()->attach($admin->id, ['papel' => 'admin']);

        VerificarTrialExpiradoJob::dispatchSync();

        $this->assertSame('trial', $tenant->fresh()->subscription_status);
        Mail::assertNothingQueued();
    }

    public function test_tenant_sem_admin_nao_quebra_e_nao_envia_email(): void
    {
        Mail::fake();

        $tenant = Tenant::create([
            'nome' => 'Barbearia Sem Admin',
            'slug' => 'barbearia-sem-admin',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->subDay(),
        ]);

        VerificarTrialExpiradoJob::dispatchSync();

        $this->assertSame('expired', $tenant->fresh()->subscription_status);
        Mail::assertNothingQueued();
    }
}
