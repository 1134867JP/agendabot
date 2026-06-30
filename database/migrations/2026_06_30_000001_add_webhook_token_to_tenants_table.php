<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('webhook_token', 64)->nullable()->after('evolution_instance');
        });

        // Gerar token para tenants existentes
        \App\Models\Tenant::whereNull('webhook_token')->each(function ($tenant) {
            $tenant->update(['webhook_token' => Str::random(32)]);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('webhook_token');
        });
    }
};
