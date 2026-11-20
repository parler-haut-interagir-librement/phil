<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures;

use Doctrine\Persistence\AbstractManagerRegistry;
use Doctrine\Persistence\ObjectManager;

/**
 * A registry of the entity managers a test builds, by name; the first is the default.
 */
final class Registry extends AbstractManagerRegistry
{
    /**
     * @param non-empty-array<string, ObjectManager> $managers
     */
    public function __construct(
        private readonly array $managers,
    ) {
        $names = array_combine(array_keys($managers), array_keys($managers));
        parent::__construct('test', [], $names, '', array_key_first($managers));
    }

    protected function getService(string $name): ObjectManager
    {
        return $this->managers[$name];
    }

    protected function resetService(string $name): void
    {
    }
}
