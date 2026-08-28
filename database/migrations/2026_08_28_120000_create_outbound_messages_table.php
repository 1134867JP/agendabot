<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbound_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversa_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('mensagem_id')->nullable()->unique()->constrained('mensagens')->nullOnDelete();
            $table->foreignId('agendamento_id')->nullable()->constrained()->nullOnDelete();
            $table->string('telefone', 40);
            $table->text('conteudo');
            $table->string('purpose', 50);
            $table->string('idempotency_key', 191)->unique();
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['purpose', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_messages');
    }
};
