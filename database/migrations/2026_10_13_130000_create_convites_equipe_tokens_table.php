<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tokens dos convites da equipe ficam fora de password_reset_tokens: o
        // convite vale dias, e um link de "esqueci a senha" (60 min) não pode
        // ganhar essa validade só por ser aberto como convite.
        Schema::create('convites_equipe_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convites_equipe_tokens');
    }
};
