<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarda o key.id devolvido pela Evolution para reconhecer o eco (fromMe)
        // das mensagens que o próprio sistema enviou.
        Schema::table('outbound_messages', function (Blueprint $table): void {
            $table->string('provider_message_id', 191)->nullable()->after('status');
            $table->index('provider_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('outbound_messages', function (Blueprint $table): void {
            $table->dropIndex(['provider_message_id']);
            $table->dropColumn('provider_message_id');
        });
    }
};
