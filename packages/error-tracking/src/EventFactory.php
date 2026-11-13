<?php

declare(strict_types=1);

namespace Phil\ErrorTracking;

use Phil\ErrorTracking\Exception\InvalidOption;
use Psr\Clock\ClockInterface;
use Throwable;

/**
 * Builds the events of an application: dated by its clock, marked with its release and environment, their frames
 * made relative to its project directory and told apart as the application's own or a dependency's — a file under
 * `vendor/`, or outside the project.
 *
 * A chain is read up to a number of exceptions and each trace up to a number of frames, so that a deep recursion
 * costs a bounded event. The arguments of the calls are never read.
 */
final readonly class EventFactory
{
    public const int DEFAULT_MAX_FRAMES = 50;

    public const int DEFAULT_MAX_CHAIN = 10;

    private string $root;

    /**
     * @throws InvalidOption when a bound is below one
     */
    public function __construct(
        string $projectDir,
        private ClockInterface $clock,
        private ?string $release = null,
        private ?string $environment = null,
        private int $maxFrames = self::DEFAULT_MAX_FRAMES,
        private int $maxChain = self::DEFAULT_MAX_CHAIN,
    ) {
        if ($maxFrames < 1 || $maxChain < 1) {
            throw InvalidOption::because('The frames and the exceptions of a chain are bounded by one at least.');
        }

        $this->root = rtrim($projectDir, '/').'/';
    }

    public function fromThrowable(Throwable $throwable, CaptureContext $context = new CaptureContext()): ErrorEvent
    {
        $records = [];

        for ($current = $throwable; $current instanceof Throwable && \count($records) < $this->maxChain; $current = $current->getPrevious()) {
            $records[] = $this->record($current);
        }

        return $this->event($records, $context);
    }

    /**
     * An event without a PHP exception: a log record, an error a browser reported.
     *
     * @param list<Frame> $frames
     */
    public function fromMessage(string $type, string $message, array $frames, CaptureContext $context = new CaptureContext()): ErrorEvent
    {
        return $this->event([new ExceptionRecord($type, $message, \array_slice($frames, 0, $this->maxFrames))], $context);
    }

    /**
     * A frame of a file of the server, relative to the project when it lies in it.
     */
    public function frame(string $file, int $line, ?string $function = null): Frame
    {
        if (!str_starts_with($file, $this->root)) {
            return new Frame($file, $line, $function);
        }

        $relative = substr($file, \strlen($this->root));

        return new Frame($relative, $line, $function, !str_starts_with($relative, 'vendor/'));
    }

    private function record(Throwable $throwable): ExceptionRecord
    {
        $trace = $throwable->getTrace();
        $frames = [$this->frame($throwable->getFile(), $throwable->getLine(), $this->callee($trace[0] ?? null))];

        for ($index = 0; isset($trace[$index]) && \count($frames) < $this->maxFrames; ++$index) {
            $call = $trace[$index];

            if (isset($call['file'])) {
                $frames[] = $this->frame($call['file'], $call['line'] ?? 0, $this->callee($trace[$index + 1] ?? null));
            }
        }

        return new ExceptionRecord($throwable::class, $throwable->getMessage(), $frames);
    }

    /**
     * @param ?array{function: string, class?: class-string, ...} $call
     */
    private function callee(?array $call): ?string
    {
        if (null === $call) {
            return null;
        }

        return isset($call['class']) ? $call['class'].'::'.$call['function'] : $call['function'];
    }

    /**
     * @param list<ExceptionRecord> $records
     */
    private function event(array $records, CaptureContext $context): ErrorEvent
    {
        return new ErrorEvent(
            sodium_bin2hex(random_bytes(16)),
            $this->clock->now(),
            $context->source,
            $context->severity,
            $records,
            $this->release,
            $this->environment,
            $context->request,
            $context->context,
        );
    }
}
