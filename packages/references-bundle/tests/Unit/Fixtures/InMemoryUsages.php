<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Tests\Unit\Fixtures;

use Phil\ReferencesBundle\Domain\Repository\UsageRepository;
use Phil\ReferencesBundle\Domain\ValueObject\Usage;
use Phil\Resource\ResourceReference;

final class InMemoryUsages implements UsageRepository
{
    /** @var array<string, list<Usage>> */
    public array $bySource = [];

    /**
     * @return list<string>
     */
    public function sources(): array
    {
        return array_keys($this->bySource);
    }

    /**
     * @return list<Usage>
     */
    public function from(string $source): array
    {
        return $this->bySource[$source] ?? [];
    }

    public function replace(ResourceReference $source, array $usages): void
    {
        $this->bySource[$source->toString()] = $usages;
    }

    public function forget(ResourceReference $source): void
    {
        unset($this->bySource[$source->toString()]);
    }

    public function usagesOf(ResourceReference $target): array
    {
        return array_values(array_filter(array_merge(...array_values($this->bySource)), static fn (Usage $usage): bool => $usage->target->equals($target)));
    }

    public function usagesOfAll(array $targets): array
    {
        return array_merge(...array_map($this->usagesOf(...), $targets));
    }

    public function targets(): iterable
    {
        $targets = [];

        foreach (array_merge(...array_values($this->bySource)) as $usage) {
            $targets[$usage->target->toString()] = $usage->target;
        }

        ksort($targets);

        return array_values($targets);
    }
}
