<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Remove sufixos @lid, @s.whatsapp.net, etc. de telefones gravados incorretamente
        DB::statement("
            UPDATE conversas
            SET telefone_cliente = regexp_replace(telefone_cliente, '@.*$', '', 'g')
            WHERE telefone_cliente LIKE '%@%'
        ");

        DB::statement("
            UPDATE clientes
            SET telefone = regexp_replace(telefone, '@.*$', '', 'g')
            WHERE telefone LIKE '%@%'
        ");

        // Atualiza nomes que ainda são o JID com @lid (placeholder)
        DB::statement("
            UPDATE clientes
            SET nome = regexp_replace(nome, '@.*$', '', 'g')
            WHERE nome LIKE '%@%'
        ");
    }

    public function down(): void {}
};
