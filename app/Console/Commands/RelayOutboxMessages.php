<?php

namespace App\Console\Commands;

use App\Services\OutboxPublisher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('outbox:work {--once} {--sleep=1} {--batch=50}')]
#[Description('Publish committed outbox messages to the queue')]
final class RelayOutboxMessages extends Command
{
    private bool $running = true;

    public function handle(OutboxPublisher $publisher): int
    {
        $this->trap(
            [SIGTERM, SIGINT, SIGQUIT],
            function (): void {
                $this->running = false;
            },
        );

        do {
            $published = $publisher->publishBatch(
                (int) $this->option('batch'),
            );

            if ($this->option('once')) {
                break;
            }

            if ($published === 0) {
                usleep(max(0, (int) $this->option('sleep')) * 1_000_000);
            }
        } while ($this->running);

        return self::SUCCESS;
    }
}
