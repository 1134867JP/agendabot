<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->foreignId('cliente_id')->nullable()->after('tenant_id')
                ->constrained('clientes')->nullOnDelete();
            $table->foreignId('profissional_id')->nullable()->after('cliente_id')
                ->constrained('profissionais')->nullOnDelete();
            $table->foreignId('servico_id')->nullable()->after('profissional_id')
                ->constrained('servicos')->nullOnDelete();
            $table->integer('duracao_minutos')->default(30)->after('servico_id');
            $table->string('opcao_extra')->nullable()->after('duracao_minutos');
            $table->timestamp('data_hora')->nullable()->after('opcao_extra');

            $table->index(['profissional_id', 'data_hora']);
            $table->index(['tenant_id', 'data_hora', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cliente_id');
            $table->dropConstrainedForeignId('profissional_id');
            $table->dropConstrainedForeignId('servico_id');
            $table->dropColumn(['duracao_minutos', 'opcao_extra', 'data_hora']);
        });
    }
};
