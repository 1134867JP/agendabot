<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('nome');
            $table->string('telefone', 30);
            $table->string('cpf', 14)->nullable();
            $table->date('data_nascimento')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'telefone']);
            $table->index(['tenant_id', 'telefone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
