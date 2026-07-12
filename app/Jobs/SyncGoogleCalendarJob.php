<?php

namespace App\Jobs;

use App\Models\Agendamento;
use App\Models\CalendarConnection;
use App\Models\OperationalEvent;
use App\Services\GoogleCalendarService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncGoogleCalendarJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [30, 120, 600];

    public function __construct(private Agendamento $agendamento) {}

    public function handle(GoogleCalendarService $calendar): void
    {
        $connection = CalendarConnection::where('tenant_id', $this->agendamento->tenant_id)->first();
        if (! $connection) {
            return;
        }
        $eventId = $calendar->sync($this->agendamento->fresh(['servico', 'recurso']), $connection);
        $this->agendamento->update(['calendar_event_id' => $eventId]);
    }

    public function failed(\Throwable $exception): void
    {
        OperationalEvent::record($this->agendamento->tenant_id, 'integration_failure', [
            'provider' => 'google_calendar', 'metadata' => ['error' => $exception->getMessage()],
        ]);
    }
}
