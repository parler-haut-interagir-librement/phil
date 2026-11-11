<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Infrastructure\Scheduler;

use Phil\MonitoringBundle\Application\Contract\HeartbeatTask;
use Phil\MonitoringBundle\Application\Heartbeats;
use Psr\Log\LoggerInterface;
use Symfony\Component\Scheduler\Event\PostRunEvent;

/**
 * Beats the task of a scheduled message implementing `HeartbeatTask` once its handler has returned. A task that is
 * not declared is logged, never thrown: the message has already run.
 */
final readonly class HeartbeatOnScheduledRun
{
    public function __construct(
        private Heartbeats $heartbeats,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(PostRunEvent $event): void
    {
        $message = $event->getMessage();

        if ($message instanceof HeartbeatTask && !$this->heartbeats->beat($message->heartbeatTask())) {
            $this->logger?->warning('The scheduled task {task} beat, but it is not declared under phil_monitoring.heartbeats.tasks.', ['task' => $message->heartbeatTask()]);
        }
    }
}
