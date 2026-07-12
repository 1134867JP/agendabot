<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarConnection extends Model
{
    protected $fillable = ['tenant_id', 'provider', 'access_token', 'refresh_token', 'expires_at', 'calendar_id'];

    protected $casts = ['access_token' => 'encrypted', 'refresh_token' => 'encrypted', 'expires_at' => 'datetime'];
}
