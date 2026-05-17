<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        DB::statement("
            ALTER TABLE agendamentos
            ADD CONSTRAINT no_double_booking
            EXCLUDE USING gist (
                recurso_id WITH =,
                tstzrange(inicio, fim) WITH &&
            )
            WHERE (status != 'cancelado')
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE agendamentos DROP CONSTRAINT IF EXISTS no_double_booking');
    }
};
