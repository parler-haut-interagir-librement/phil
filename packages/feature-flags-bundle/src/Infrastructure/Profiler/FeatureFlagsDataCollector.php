<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Infrastructure\Profiler;

use Override;
use Phil\FeatureFlagsBundle\Application\Flag\FlagEvaluator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Throwable;

/**
 * The flags a request evaluated — enabled or not, why, which variant —, in the Symfony profiler; the bundle tags it
 * with its template, `TEMPLATE`. The state of each circuit is the panel of `phil/circuit-breaker-bundle`.
 */
final class FeatureFlagsDataCollector extends DataCollector
{
    public const string TEMPLATE = '@PhilFeatureFlags/profiler/collector.html.twig';

    public function __construct(
        private readonly FlagEvaluator $evaluator,
    ) {
    }

    #[Override]
    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        $flags = [];

        foreach ($this->evaluator->evaluated() as $name => $evaluation) {
            $flags[$name] = ['enabled' => $evaluation->enabled, 'reason' => $evaluation->reason->value, 'variant' => $evaluation->variant?->value, 'error' => $evaluation->errorCode?->value];
        }

        $this->data = ['report' => new FlagsReport($flags)];
    }

    #[Override]
    public function reset(): void
    {
        $this->data = [];
    }

    #[Override]
    public function getName(): string
    {
        return 'phil_feature_flags';
    }

    /**
     * @return array<string, array{enabled: bool, reason: string, variant: ?string, error: ?string}>
     */
    public function flags(): array
    {
        return $this->report()->flags ?? [];
    }

    public function enabledCount(): int
    {
        return \count(array_filter($this->flags(), static fn (array $flag): bool => $flag['enabled']));
    }

    private function report(): ?FlagsReport
    {
        $report = $this->data['report'] ?? null;

        return $report instanceof FlagsReport ? $report : null;
    }
}
