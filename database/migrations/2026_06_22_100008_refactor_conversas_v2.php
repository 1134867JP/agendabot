<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversas', function (Blueprint $table) {
            $table->foreignId('cliente_id')->nullable()->after('tenant_id')
                ->constrained('clientes')->nullOnDelete();
            $table->enum('status_v2', ['ativa', 'aguardando_humano', 'em_atendimento_humano', 'encerrada'])
                ->default('ativa')->after('telefone_cliente');
            $table->timestamp('ultima_mensagem_em')->nullable()->after('status_v2');

            $table->index(['tenant_id', 'status_v2']);
        });
    }

    public function down(): void
    {
        Schema::table('conversas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cliente_id');
            $table->dropColumn(['status_v2', 'ultima_mensagem_em']);
        });
    }
};
