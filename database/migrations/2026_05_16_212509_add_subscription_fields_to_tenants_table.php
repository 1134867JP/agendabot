<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('subscription_status')->default('trial')->after('ativo');
            $table->timestamp('trial_ends_at')->nullable()->after('subscription_status');
            $table->timestamp('subscription_ends_at')->nullable()->after('trial_ends_at');
            $table->string('asaas_customer_id')->nullable()->after('subscription_ends_at');
            $table->string('asaas_subscription_id')->nullable()->after('asaas_customer_id');
            $table->string('plano')->default('basico')->after('asaas_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'subscription_status',
                'trial_ends_at',
                'subscription_ends_at',
                'asaas_customer_id',
                'asaas_subscription_id',
                'plano',
            ]);
        });
    }
};
