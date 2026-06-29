<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('conversas', function (Blueprint $table) {
            $table->timestamp('ultima_leitura_em')->nullable()->after('ultima_mensagem_em');
        });
    }

    public function down(): void
    {
        Schema::table('conversas', function (Blueprint $table) {
            $table->dropColumn('ultima_leitura_em');
        });
    }
};
