<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agendamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('recurso_id')->constrained();
            $table->string('cliente_nome');
            $table->string('cliente_telefone', 20);
            $table->timestampTz('inicio');
            $table->timestampTz('fim');
            $table->string('status')->default('confirmado');
            $table->decimal('valor_total', 8, 2)->nullable();
            $table->timestamps();

            $table->index(['recurso_id', 'inicio', 'fim']);
            $table->index(['tenant_id', 'inicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agendamentos');
    }
};
