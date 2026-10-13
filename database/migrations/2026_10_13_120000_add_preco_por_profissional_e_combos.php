<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preço e duração podem variar por profissional (ex.: barbeiro sênior).
        // Nulo = usa o valor e a duração padrão do serviço.
        Schema::table('profissional_servico', function (Blueprint $table): void {
            $table->decimal('valor', 8, 2)->nullable();
            $table->integer('duracao_minutos')->nullable();
        });

        // Um agendamento pode ter vários serviços (ex.: corte + barba). Nome,
        // valor e duração ficam congelados no momento da marcação.
        Schema::create('agendamento_servicos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agendamento_id')->constrained('agendamentos')->cascadeOnDelete();
            $table->foreignId('servico_id')->nullable()->constrained('servicos')->nullOnDelete();
            $table->string('nome');
            $table->decimal('valor', 8, 2)->nullable();
            $table->integer('duracao_minutos');
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();

            $table->index(['agendamento_id', 'ordem']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agendamento_servicos');
        Schema::table('profissional_servico', function (Blueprint $table): void {
            $table->dropColumn(['valor', 'duracao_minutos']);
        });
    }
};
