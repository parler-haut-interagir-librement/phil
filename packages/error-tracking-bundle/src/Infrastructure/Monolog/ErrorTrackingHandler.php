<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Infrastructure\Monolog;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Override;
use Phil\ErrorTracking\CaptureContext;
use Phil\ErrorTracking\EventFactory;
use Phil\ErrorTracking\Severity;
use Phil\ErrorTracking\Source;
use Phil\ErrorTrackingBundle\Application\SafeCapture;
use Throwable;

/**
 * A Monolog handler that captures the records of a level and above, `error` by default: the exception of the record's
 * `exception` key when there is one, else the record itself, its message read before the placeholders are replaced so
 * that `Order {id} failed` groups as one. The record's channel is a tag; its other context is left out, being the
 * application's free data. `critical` and above are fatal.
 *
 *     monolog:
 *         handlers:
 *             errors:
 *                 type: service
 *                 id: phil_error_tracking.monolog_handler
 */
final class ErrorTrackingHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly SafeCapture $capture,
        private readonly EventFactory $factory,
        int|string|Level $level = Level::Error,
    ) {
        parent::__construct($level);
    }

    #[Override]
    protected function write(LogRecord $record): void
    {
        $context = new CaptureContext(Source::Log, $record->level->isHigherThan(Level::Error) ? Severity::Fatal : ($record->level->isLowerThan(Level::Error) ? Severity::Warning : Severity::Error), null, ['channel' => $record->channel]);
        $exception = $record->context['exception'] ?? null;

        if ($exception instanceof Throwable) {
            $this->capture->throwable($exception, $context);

            return;
        }

        $this->capture->event($this->factory->fromMessage('Log record', $record->message, [], $context));
    }
}
