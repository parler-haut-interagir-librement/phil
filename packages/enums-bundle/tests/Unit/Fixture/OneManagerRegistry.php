<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Fixture;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use LogicException;

/**
 * A registry of one entity manager, which manages every class it maps.
 */
final readonly class OneManagerRegistry implements ManagerRegistry
{
    public function __construct(
        private ObjectManager $manager,
    ) {
    }

    public function getManagerForClass(string $class): ?ObjectManager
    {
        return $this->manager->getMetadataFactory()->hasMetadataFor($class) ? $this->manager : null;
    }

    public function getDefaultConnectionName(): string
    {
        return 'default';
    }

    public function getConnection(?string $name = null): object
    {
        throw new LogicException('No connection.');
    }

    public function getConnections(): array
    {
        return [];
    }

    public function getConnectionNames(): array
    {
        return [];
    }

    public function getDefaultManagerName(): string
    {
        return 'default';
    }

    public function getManager(?string $name = null): ObjectManager
    {
        return $this->manager;
    }

    public function getManagers(): array
    {
        return ['default' => $this->manager];
    }

    public function resetManager(?string $name = null): ObjectManager
    {
        return $this->manager;
    }

    public function getManagerNames(): array
    {
        return ['default' => 'default'];
    }

    public function getRepository(string $persistentObject, ?string $persistentManagerName = null): ObjectRepository
    {
        return $this->manager->getRepository($persistentObject);
    }
}
