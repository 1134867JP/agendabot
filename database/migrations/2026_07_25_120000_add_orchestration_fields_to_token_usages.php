<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('token_usages', function (Blueprint $table) {
            $table->string('provider', 30)->default('claude')->after('tenant_id');
            $table->decimal('cost_usd', 12, 8)->default(0)->after('cache_read_input_tokens');
            $table->unsignedInteger('latency_ms')->nullable()->after('cost_usd');
            $table->string('request_id', 150)->nullable()->after('latency_ms');
            $table->index(['tenant_id', 'provider', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('token_usages', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'provider', 'created_at']);
            $table->dropColumn(['provider', 'cost_usd', 'latency_ms', 'request_id']);
        });
    }
};
