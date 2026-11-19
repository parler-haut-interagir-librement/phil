<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Infrastructure\Monolog;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Override;
use Phil\ErrorTracking\CaptureContext;
use Phil\ErrorTracking\ErrorEvent;
use Phil\ErrorTracking\EventFactory;
use Phil\ErrorTracking\ExceptionRecord;
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
 * A processor that runs before the handlers may have replaced the exception by an array — its `class`, `message`,
 * `file`, `line` and `previous` exception the same way —, as the redaction of phil/privacy-bundle does. That array is
 * read as the chain it describes, each exception with the one frame of its file and line, so that the event keeps its
 * class and its place and groups by them; an exception the request listener captured already is not taken again.
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

        if ($this->isDescribed($exception)) {
            $this->capture->exception($exception['class'], $exception['file'], $exception['line'], fn (): ErrorEvent => $this->event($exception, $context));

            return;
        }

        $this->capture->event($this->factory->fromMessage('Log record', $record->message, [], $context));
    }

    /**
     * Whether a value describes an exception: an array with its class, its file and its line.
     *
     * @phpstan-assert-if-true array{class: string, file: string, line: int, message?: mixed, previous?: mixed} $value
     */
    private function isDescribed(mixed $value): bool
    {
        return \is_array($value) && \is_string($value['class'] ?? null) && \is_string($value['file'] ?? null) && \is_int($value['line'] ?? null);
    }

    /**
     * The event of the chain an array describes, the outer exception first, each with the frame of its file and line,
     * bounded like a chain of throwables.
     *
     * @param array{class: string, file: string, line: int, message?: mixed, previous?: mixed} $exception
     */
    private function event(array $exception, CaptureContext $context): ErrorEvent
    {
        $records = [];
        $current = $exception;

        do {
            $message = $current['message'] ?? null;
            $records[] = new ExceptionRecord($current['class'], \is_string($message) ? $message : '', [$this->factory->frame($current['file'], $current['line'])]);
            $current = $current['previous'] ?? null;
        } while ($this->isDescribed($current) && \count($records) < EventFactory::DEFAULT_MAX_CHAIN);

        $event = $this->factory->fromMessage($records[0]->type, $records[0]->message, $records[0]->frames, $context);

        return $event->with($records, $event->request, $event->context);
    }
}
