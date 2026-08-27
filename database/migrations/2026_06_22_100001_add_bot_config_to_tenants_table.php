<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('ramo_negocio')->nullable()->after('slug');
            $table->text('descricao_negocio')->nullable()->after('ramo_negocio');
            $table->string('cidade')->nullable()->after('descricao_negocio');
            $table->string('endereco')->nullable()->after('cidade');
            $table->json('horarios_funcionamento')->nullable()->after('endereco');
            $table->string('nome_agente')->default('Bia')->after('horarios_funcionamento');
            $table->enum('tom_voz', ['formal', 'semiformal', 'descontraido'])->default('semiformal')->after('nome_agente');
            $table->text('instrucoes_extras')->nullable()->after('tom_voz');
            $table->boolean('bot_ativo')->default(true)->after('instrucoes_extras');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'ramo_negocio', 'descricao_negocio', 'cidade', 'endereco',
                'horarios_funcionamento', 'nome_agente', 'tom_voz', 'instrucoes_extras', 'bot_ativo',
            ]);
        });
    }
};
