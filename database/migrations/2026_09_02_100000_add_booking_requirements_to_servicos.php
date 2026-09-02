<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicos', function (Blueprint $table): void {
            $table->boolean('requer_profissional')->default(true)->after('duracao_minutos');
            $table->boolean('requer_recurso')->default(false)->after('requer_profissional');
        });

        Schema::create('recurso_servico', function (Blueprint $table): void {
            $table->foreignId('recurso_id')->constrained()->cascadeOnDelete();
            $table->foreignId('servico_id')->constrained()->cascadeOnDelete();
            $table->primary(['recurso_id', 'servico_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurso_servico');

        Schema::table('servicos', function (Blueprint $table): void {
            $table->dropColumn(['requer_profissional', 'requer_recurso']);
        });
    }
};
