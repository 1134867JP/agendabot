<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\CalendarConnection;
use Illuminate\Support\Facades\Http;

class GoogleCalendarService
{
    public function sync(Agendamento $agendamento, CalendarConnection $connection): string
    {
        $this->refreshIfNeeded($connection);
        $start = $agendamento->data_hora ?? $agendamento->inicio;
        $end = $agendamento->fim ?? $start?->copy()->addMinutes($agendamento->duracao_minutos ?? 30);
        $payload = [
            'summary' => ($agendamento->servico?->nome ?? $agendamento->recurso?->nome ?? 'Agendamento').' — '.$agendamento->cliente_nome,
            'description' => "AgendaBot #{$agendamento->id}",
            'start' => ['dateTime' => $start->toRfc3339String(), 'timeZone' => config('app.timezone')],
            'end' => ['dateTime' => $end->toRfc3339String(), 'timeZone' => config('app.timezone')],
        ];
        $base = 'https://www.googleapis.com/calendar/v3/calendars/'.urlencode($connection->calendar_id).'/events';
        $request = Http::withToken($connection->access_token)->acceptJson();
        $response = $agendamento->calendar_event_id
            ? $request->put("{$base}/{$agendamento->calendar_event_id}", $payload)
            : $request->post($base, $payload);
        $response->throw();

        return (string) $response->json('id');
    }

    private function refreshIfNeeded(CalendarConnection $connection): void
    {
        if (! $connection->expires_at?->isPast() || ! $connection->refresh_token) {
            return;
        }
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $connection->refresh_token,
            'grant_type' => 'refresh_token',
        ])->throw();
        $connection->update([
            'access_token' => $response->json('access_token'),
            'expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
        ]);
    }
}
