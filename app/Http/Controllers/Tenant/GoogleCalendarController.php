<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\CalendarConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleCalendarController extends Controller
{
    public function connect(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put('google_calendar_state', $state);
        $query = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => route('tenant.calendar.callback'),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar.events',
            'access_type' => 'offline', 'prompt' => 'consent', 'state' => $state,
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(hash_equals((string) $request->session()->pull('google_calendar_state'), (string) $request->state), 403);
        $token = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.client_id'), 'client_secret' => config('services.google.client_secret'),
            'code' => $request->string('code'), 'grant_type' => 'authorization_code',
            'redirect_uri' => route('tenant.calendar.callback'),
        ])->throw()->json();
        CalendarConnection::updateOrCreate(['tenant_id' => app('tenant')->id], [
            'access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'] ?? null,
            'expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600)), 'provider' => 'google',
        ]);

        return redirect()->route('tenant.configuracoes.index')->with('success', 'Google Calendar conectado.');
    }

    public function disconnect(): RedirectResponse
    {
        CalendarConnection::where('tenant_id', app('tenant')->id)->delete();

        return back()->with('success', 'Google Calendar desconectado.');
    }
}
