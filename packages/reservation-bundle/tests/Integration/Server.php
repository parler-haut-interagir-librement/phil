<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\ReservationBundle\Application\Reservations;
use Phil\ReservationBundle\Infrastructure\Doctrine\DoctrineReservationRepository;
use Phil\ReservationBundle\Infrastructure\Doctrine\DoctrineResourceRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;

/**
 * An entity manager of the bundle's mapping on the server of a URL, for the integration tests and the processes they
 * start.
 */
final readonly class Server
{
    public function __construct(
        public EntityManager $entityManager,
    ) {
    }

    public static function connect(string $url): self
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 2).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $parameters = new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url);

        return new self(new EntityManager(DriverManager::getConnection($parameters), $configuration));
    }

    /**
     * Drops the bundle's tables, if they are there, and creates them again from the mapping.
     */
    public function recreate(): void
    {
        $tool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function resources(): DoctrineResourceRepository
    {
        return new DoctrineResourceRepository($this->entityManager);
    }

    public function repository(): DoctrineReservationRepository
    {
        return new DoctrineReservationRepository($this->entityManager);
    }

    public function reservations(ClockInterface $clock = new NativeClock()): Reservations
    {
        return new Reservations($this->resources(), $this->repository(), $clock);
    }
}
