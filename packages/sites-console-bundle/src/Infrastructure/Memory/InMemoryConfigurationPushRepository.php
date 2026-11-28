<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Memory;

use Override;
use Phil\SitesConsoleBundle\Domain\Contract\ConfigurationPushRepository;
use Phil\SitesConsoleBundle\Domain\Model\ConfigurationPush;

/**
 * The pushes in memory, for tests and demonstrations.
 */
final class InMemoryConfigurationPushRepository implements ConfigurationPushRepository
{
    /** @var array<string, ConfigurationPush> */
    private array $pushes = [];

    #[Override]
    public function get(string $id): ?ConfigurationPush
    {
        return $this->pushes[$id] ?? null;
    }

    #[Override]
    public function save(ConfigurationPush $push): void
    {
        $this->pushes[$push->id()] = $push;
    }

    #[Override]
    public function recent(int $limit): array
    {
        $pushes = $this->pushes;
        krsort($pushes, \SORT_STRING);

        return \array_slice(array_values($pushes), 0, max(1, $limit));
    }
}
