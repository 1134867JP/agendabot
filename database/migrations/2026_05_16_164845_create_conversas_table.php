<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('telefone_cliente', 20);
            $table->string('etapa')->default('idle');
            $table->json('contexto')->nullable();
            $table->json('historico_mensagens')->nullable();
            $table->timestamp('atualizado_em')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'telefone_cliente']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversas');
    }
};
