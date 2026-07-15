<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * A coluna foi criada como JSON (migration add_bot_config), mas o único ponto que a
     * escreve/lê (ConfiguracaoController + montador de prompt do bot) sempre a trata como
     * texto livre ("Seg–Sex 09:00–19:00"). Isso torna gravações inválidas no Postgres.
     * Aqui alinhamos o schema ao uso real: text simples.
     */
    public function up(): void
    {
        // #>> '{}' extrai o conteúdo de uma string JSON como texto puro (sem aspas);
        // valores nulos permanecem nulos.
        DB::statement("ALTER TABLE tenants ALTER COLUMN horarios_funcionamento TYPE text USING horarios_funcionamento #>> '{}'");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tenants ALTER COLUMN horarios_funcionamento TYPE json USING to_json(horarios_funcionamento)');
    }
};
