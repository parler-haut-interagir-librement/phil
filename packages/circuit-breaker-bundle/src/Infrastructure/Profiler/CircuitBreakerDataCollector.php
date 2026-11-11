<?php

declare(strict_types=1);

namespace Phil\CircuitBreakerBundle\Infrastructure\Profiler;

use Override;
use Phil\CircuitBreaker\CircuitBreakers;
use Phil\CircuitBreaker\CircuitState;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Throwable;

/**
 * The state of each declared circuit at the end of a request, in the Symfony profiler; the bundle tags it with its
 * template, `TEMPLATE`.
 */
final class CircuitBreakerDataCollector extends DataCollector
{
    public const string TEMPLATE = '@PhilCircuitBreaker/profiler/collector.html.twig';

    public function __construct(
        private readonly CircuitBreakers $circuits,
    ) {
    }

    #[Override]
    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        $circuits = [];

        foreach ($this->circuits->keys() as $key) {
            $circuits[$key->value] = $this->circuits->get($key)->state()->value;
        }

        $this->data = ['report' => new CircuitsReport($circuits)];
    }

    #[Override]
    public function reset(): void
    {
        $this->data = [];
    }

    #[Override]
    public function getName(): string
    {
        return 'phil_circuit_breaker';
    }

    /**
     * @return array<string, string> circuit key => state
     */
    public function circuits(): array
    {
        $report = $this->data['report'] ?? null;

        return $report instanceof CircuitsReport ? $report->circuits : [];
    }

    /**
     * How many circuits are not closed: open, or half-open and letting trial calls through.
     */
    public function trippedCount(): int
    {
        return \count(array_filter($this->circuits(), static fn (string $state): bool => CircuitState::Closed->value !== $state));
    }
}
