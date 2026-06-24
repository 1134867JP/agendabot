<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversas', function (Blueprint $table) {
            $table->string('telefone_cliente', 50)->change();
        });

        Schema::table('agendamentos', function (Blueprint $table) {
            $table->string('cliente_telefone', 50)->change();
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('telefone_whatsapp', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('conversas', function (Blueprint $table) {
            $table->string('telefone_cliente', 20)->change();
        });

        Schema::table('agendamentos', function (Blueprint $table) {
            $table->string('cliente_telefone', 20)->change();
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('telefone_whatsapp', 20)->nullable()->change();
        });
    }
};
