<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Application;

use Closure;
use Phil\ErrorTracking\Capture;
use Phil\ErrorTracking\CaptureContext;
use Phil\ErrorTracking\ErrorEvent;
use Phil\ErrorTracking\ErrorTracker;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;
use WeakMap;

/**
 * The tracker as every capture point calls it: a capture never throws, never starts while another runs, and never
 * takes the same throwable twice.
 *
 * A store that fails while writing an error raises an error of its own; logging it would reach the log handler, which
 * would capture it, which would fail again. While a capture runs, every other capture is refused, so the failure is
 * logged once, by its class only, and the loop stops there. The exception a request listener caught and the log
 * record of the same exception are taken once: the throwables already captured are remembered until the services are
 * reset, at the end of each request of a worker. A log processor may have turned the record's exception into an
 * array — its class, its file and its line — before the handler reads it; the class, file and line of each throwable
 * captured are remembered too, up to {@see self::MAX_SIGNATURES}, and the first such array that matches one is
 * taken for it and left out.
 */
final class SafeCapture implements ResetInterface
{
    public const int MAX_SIGNATURES = 100;

    private bool $capturing = false;

    /**
     * @var array<string, true> the class, file and line of each throwable captured since the last reset, not yet matched
     *                          by an exception known by its place
     */
    private array $signatures = [];

    /**
     * @var WeakMap<Throwable, true>
     */
    private WeakMap $seen;

    /**
     * @param list<class-string<Throwable>> $ignored exceptions never captured, with their subclasses
     */
    public function __construct(
        private readonly ErrorTracker $tracker,
        private readonly array $ignored = [],
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->seen = new WeakMap();
    }

    public function throwable(Throwable $throwable, CaptureContext $context): ?Capture
    {
        if ($this->capturing || true === ($this->seen[$throwable] ?? false) || $this->isIgnored($throwable)) {
            return null;
        }

        $this->seen[$throwable] = true;
        $this->remember($throwable::class, $throwable->getFile(), $throwable->getLine());

        return $this->guarded(fn (): ?Capture => $this->tracker->captureThrowable($throwable, $context));
    }

    public function event(ErrorEvent $event): ?Capture
    {
        return $this->capturing ? null : $this->guarded(fn (): ?Capture => $this->tracker->capture($event));
    }

    /**
     * The event of an exception known only by its class, its file and its line — the array a log processor left in
     * place of the throwable —, refused when it is ignored, or when a throwable of that class, file and line was
     * captured since the last reset and not yet matched: the log record of an exception a listener already took.
     *
     * @param Closure(): ErrorEvent $event
     */
    public function exception(string $class, string $file, int $line, Closure $event): ?Capture
    {
        $signature = $class.'|'.$file.':'.$line;

        if (true === ($this->signatures[$signature] ?? false)) {
            unset($this->signatures[$signature]);

            return null;
        }

        return $this->capturing || $this->isIgnored($class) ? null : $this->guarded(fn (): ?Capture => $this->tracker->capture($event()));
    }

    /**
     * Whether a throwable, or an exception class, is never captured.
     */
    public function isIgnored(Throwable|string $exception): bool
    {
        return array_any($this->ignored, static fn (string $class): bool => is_a($exception, $class, true));
    }

    public function reset(): void
    {
        $this->seen = new WeakMap();
        $this->signatures = [];
    }

    private function remember(string $class, string $file, int $line): void
    {
        if (\count($this->signatures) >= self::MAX_SIGNATURES) {
            unset($this->signatures[array_key_first($this->signatures)]);
        }

        $this->signatures[$class.'|'.$file.':'.$line] = true;
    }

    /**
     * @param Closure(): ?Capture $capture
     */
    private function guarded(Closure $capture): ?Capture
    {
        $this->capturing = true;

        try {
            return $capture();
        } catch (Throwable $throwable) {
            $this->logger?->warning('An error could not be captured: {failure}.', ['failure' => $throwable::class]);

            return null;
        } finally {
            $this->capturing = false;
        }
    }
}
