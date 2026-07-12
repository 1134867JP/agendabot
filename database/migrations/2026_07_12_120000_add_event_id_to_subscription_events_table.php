<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_events', function (Blueprint $table) {
            $table->string('event_id')->nullable()->unique()->after('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_events', function (Blueprint $table) {
            $table->dropUnique(['event_id']);
            $table->dropColumn('event_id');
        });
    }
};
