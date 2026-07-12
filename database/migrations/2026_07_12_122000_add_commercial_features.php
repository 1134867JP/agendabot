<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('profissional_id')->nullable()->constrained('profissionais')->nullOnDelete();
            $table->foreignId('servico_id')->nullable()->constrained('servicos')->nullOnDelete();
            $table->string('cliente_nome');
            $table->string('cliente_telefone', 40);
            $table->date('data_preferida')->nullable();
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fim')->nullable();
            $table->string('status')->default('aguardando');
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'data_preferida']);
        });

        Schema::create('calendar_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider')->default('google');
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('calendar_id')->default('primary');
            $table->timestamps();
        });

        Schema::table('agendamentos', function (Blueprint $table) {
            $table->string('deposit_status')->default('not_required');
            $table->decimal('deposit_amount', 8, 2)->nullable();
            $table->string('deposit_payment_id')->nullable()->unique();
            $table->text('deposit_payment_url')->nullable();
            $table->string('calendar_event_id')->nullable()->index();
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->timestamp('confirmed_by_client_at')->nullable();
            $table->boolean('no_show')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->dropColumn(['deposit_status', 'deposit_amount', 'deposit_payment_id', 'deposit_payment_url',
                'calendar_event_id', 'confirmation_sent_at', 'confirmed_by_client_at', 'no_show']);
        });
        Schema::dropIfExists('calendar_connections');
        Schema::dropIfExists('waitlist_entries');
    }
};
