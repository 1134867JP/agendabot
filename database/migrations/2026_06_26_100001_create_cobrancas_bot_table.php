<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobrancas_bot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('periodo', 7); // formato: '2026-06'
            $table->unsignedInteger('quantidade_agendamentos');
            $table->decimal('valor_total', 8, 2);
            $table->string('asaas_charge_id')->nullable();
            $table->enum('status', ['pendente', 'pago', 'falhou'])->default('pendente');
            $table->timestamps();

            $table->unique(['tenant_id', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobrancas_bot');
    }
};
