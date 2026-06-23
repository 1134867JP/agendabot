<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversa_id')->constrained()->cascadeOnDelete();
            $table->enum('remetente', ['cliente', 'bot', 'humano']);
            $table->text('conteudo');
            $table->string('evolution_message_id')->nullable()->unique();
            $table->timestamp('enviada_em')->nullable();
            $table->timestamps();

            $table->index(['conversa_id', 'enviada_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensagens');
    }
};
