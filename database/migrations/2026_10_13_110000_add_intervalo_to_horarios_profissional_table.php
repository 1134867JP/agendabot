<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pausa fixa dentro do expediente (ex.: almoço das 12h às 13h).
        Schema::table('horarios_profissional', function (Blueprint $table): void {
            $table->time('intervalo_inicio')->nullable()->after('hora_fim');
            $table->time('intervalo_fim')->nullable()->after('intervalo_inicio');
        });
    }

    public function down(): void
    {
        Schema::table('horarios_profissional', function (Blueprint $table): void {
            $table->dropColumn(['intervalo_inicio', 'intervalo_fim']);
        });
    }
};
