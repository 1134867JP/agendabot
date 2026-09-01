<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // O antigo "operador" é a recepção: mantém os acessos existentes, mas
        // passa a ter um nome de papel coerente com a operação de barbearia.
        DB::table('tenant_users')->where('papel', 'operador')->update(['papel' => 'recepcionista']);

        Schema::table('profissionais', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('tenant_id')
                ->constrained()->nullOnDelete();
            $table->unique(['tenant_id', 'user_id']);
        });

        Schema::table('conversas', function (Blueprint $table): void {
            $table->foreignId('profissional_id')->nullable()->after('cliente_id')
                ->constrained('profissionais')->nullOnDelete();
            $table->index(['tenant_id', 'profissional_id', 'ultima_mensagem_em']);
        });
    }

    public function down(): void
    {
        Schema::table('conversas', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'profissional_id', 'ultima_mensagem_em']);
            $table->dropConstrainedForeignId('profissional_id');
        });

        Schema::table('profissionais', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'user_id']);
            $table->dropConstrainedForeignId('user_id');
        });

        DB::table('tenant_users')->where('papel', 'recepcionista')->update(['papel' => 'operador']);
    }
};
