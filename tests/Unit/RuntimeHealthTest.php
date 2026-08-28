<?php

namespace Tests\Unit;

use App\Support\RuntimeHealth;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RuntimeHealthTest extends TestCase
{
    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    public function test_runtime_fica_pronto_com_todos_os_heartbeats(): void
    {
        config(['queue.monitoring.workers' => ['interactive', 'batch']]);

        RuntimeHealth::touchWorker('interactive');
        RuntimeHealth::touchWorker('batch');
        RuntimeHealth::touchScheduler();

        $status = RuntimeHealth::status();

        $this->assertTrue($status['ready']);
        $this->assertSame('ok', $status['workers']['interactive']['status']);
        $this->assertSame('ok', $status['workers']['batch']['status']);
        $this->assertSame('ok', $status['scheduler']['status']);
    }

    public function test_runtime_identifica_heartbeat_ausente(): void
    {
        config(['queue.monitoring.workers' => ['interactive', 'batch']]);

        RuntimeHealth::touchWorker('interactive');
        RuntimeHealth::touchScheduler();

        $status = RuntimeHealth::status();

        $this->assertFalse($status['ready']);
        $this->assertSame('missing', $status['workers']['batch']['status']);
    }

    public function test_runtime_identifica_heartbeat_atrasado(): void
    {
        config([
            'queue.monitoring.workers' => ['interactive'],
            'queue.monitoring.worker_stale_after_seconds' => 60,
            'queue.monitoring.scheduler_stale_after_seconds' => 150,
        ]);

        RuntimeHealth::touchWorker('interactive');
        RuntimeHealth::touchScheduler();
        $this->travel(61)->seconds();

        $status = RuntimeHealth::status();

        $this->assertFalse($status['ready']);
        $this->assertSame('stale', $status['workers']['interactive']['status']);
        $this->assertSame('ok', $status['scheduler']['status']);
    }

    public function test_loop_do_worker_registra_heartbeat_com_nome(): void
    {
        config(['queue.monitoring.workers' => ['interactive']]);

        event(new Looping('database', 'default', new WorkerOptions(name: 'interactive')));

        $this->assertSame('ok', RuntimeHealth::status()['workers']['interactive']['status']);
    }
}
