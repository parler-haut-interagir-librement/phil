<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\Profiler;

use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Throwable;

/**
 * The media panel of the Symfony profiler: the images a request rendered and how long each took, and what it read
 * from the storage — a rendering found there is one not drawn again —, wrote and deleted. The bundle tags it with its
 * template, `TEMPLATE`, in debug.
 */
final class MediaDataCollector extends DataCollector
{
    public const string TEMPLATE = '@PhilMedia/profiler/collector.html.twig';

    public function __construct(
        private readonly MediaTrace $trace,
    ) {
    }

    #[Override]
    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        $this->data = ['report' => new MediaReport($this->trace->storage(), $this->trace->renders())];
    }

    #[Override]
    public function reset(): void
    {
        $this->data = [];
        $this->trace->reset();
    }

    #[Override]
    public function getName(): string
    {
        return 'phil_media';
    }

    /**
     * @return list<array{operation: string, key: string, bytes: int, found: bool}>
     */
    public function storage(): array
    {
        return $this->report()->storage ?? [];
    }

    /**
     * @return list<array{format: string, type: string, bytes: int, milliseconds: float}>
     */
    public function renders(): array
    {
        return $this->report()->renders ?? [];
    }

    public function renderTime(): float
    {
        return array_sum(array_column($this->renders(), 'milliseconds'));
    }

    /**
     * How many renderings were read back from the storage rather than drawn.
     */
    public function hits(): int
    {
        return \count(array_filter($this->storage(), static fn (array $entry): bool => 'read' === $entry['operation'] && $entry['found'] && str_starts_with($entry['key'], 'formats/')));
    }

    private function report(): ?MediaReport
    {
        $report = $this->data['report'] ?? null;

        return $report instanceof MediaReport ? $report : null;
    }
}
