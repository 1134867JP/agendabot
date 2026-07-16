<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bloqueios_agenda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('profissional_id')->nullable()->constrained('profissionais')->cascadeOnDelete();
            $table->foreignId('recurso_id')->nullable()->constrained('recursos')->cascadeOnDelete();
            $table->dateTimeTz('inicio');
            $table->dateTimeTz('fim');
            $table->string('motivo', 120)->nullable();
            $table->timestamps();

            $table->index(['profissional_id', 'inicio', 'fim']);
            $table->index(['recurso_id', 'inicio', 'fim']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bloqueios_agenda');
    }
};
