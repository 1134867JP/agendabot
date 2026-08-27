<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // agendamento = bot fecha o agendamento sozinho (padrão atual)
            // triagem     = bot só coleta dados e transfere para a atendente humana
            $table->string('modo_bot')->default('agendamento')->after('bot_ativo');

            // Horário em que a atendente humana atende. Array indexado 0-6 (Dom->Sáb),
            // cada item { ativo: bool, abertura: "HH:MM", fechamento: "HH:MM" }.
            $table->json('horario_atendimento')->nullable()->after('modo_bot');

            // Mensagem opcional exibida fora do horário. Vazia => gerada a partir do horário.
            $table->text('mensagem_fora_horario')->nullable()->after('horario_atendimento');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['modo_bot', 'horario_atendimento', 'mensagem_fora_horario']);
        });
    }
};
