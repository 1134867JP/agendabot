<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionEvent extends Model
{
    protected $fillable = [
        'tenant_id', 'event_id', 'tipo', 'asaas_payment_id', 'valor', 'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'valor' => 'decimal:2',
    ];
}
