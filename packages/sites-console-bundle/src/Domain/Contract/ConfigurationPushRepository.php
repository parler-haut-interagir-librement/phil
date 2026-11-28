<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Contract;

use Phil\SitesConsoleBundle\Domain\Model\ConfigurationPush;

/**
 * The configuration pushes and their deliveries.
 */
interface ConfigurationPushRepository
{
    public function get(string $id): ?ConfigurationPush;

    public function save(ConfigurationPush $push): void;

    /**
     * @return list<ConfigurationPush> the newest first
     */
    public function recent(int $limit): array;
}
