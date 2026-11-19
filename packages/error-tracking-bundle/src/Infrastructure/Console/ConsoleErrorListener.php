<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Infrastructure\Console;

use Phil\ErrorTracking\CaptureContext;
use Phil\ErrorTracking\Severity;
use Phil\ErrorTracking\Source;
use Phil\ErrorTrackingBundle\Application\SafeCapture;
use Symfony\Component\Console\Event\ConsoleErrorEvent;

/**
 * Captures the error a console command ended in, tagged with the command's name and its exit code. The arguments of
 * the command are left out: they may carry an address, a name or a secret.
 */
final readonly class ConsoleErrorListener
{
    public function __construct(
        private SafeCapture $capture,
    ) {
    }

    public function onConsoleError(ConsoleErrorEvent $event): void
    {
        $this->capture->throwable($event->getError(), new CaptureContext(Source::Console, Severity::Error, null, [
            'command' => $event->getCommand()?->getName(),
            'exit_code' => $event->getExitCode(),
        ]));
    }
}
