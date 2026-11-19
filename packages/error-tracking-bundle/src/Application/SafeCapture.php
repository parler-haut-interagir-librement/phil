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
 * reset, at the end of each request of a worker.
 */
final class SafeCapture implements ResetInterface
{
    private bool $capturing = false;

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

        return $this->guarded(fn (): ?Capture => $this->tracker->captureThrowable($throwable, $context));
    }

    public function event(ErrorEvent $event): ?Capture
    {
        return $this->capturing ? null : $this->guarded(fn (): ?Capture => $this->tracker->capture($event));
    }

    public function isIgnored(Throwable $throwable): bool
    {
        return array_any($this->ignored, static fn (string $class): bool => $throwable instanceof $class);
    }

    public function reset(): void
    {
        $this->seen = new WeakMap();
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
