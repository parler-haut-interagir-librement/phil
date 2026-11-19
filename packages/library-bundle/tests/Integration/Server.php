<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\LibraryBundle\Application\Catalogue;
use Phil\LibraryBundle\Application\LoanPolicy;
use Phil\LibraryBundle\Application\Loans;
use Phil\LibraryBundle\Infrastructure\Doctrine\DbalTitleSearch;
use Phil\LibraryBundle\Infrastructure\Doctrine\DoctrineCopyRepository;
use Phil\LibraryBundle\Infrastructure\Doctrine\DoctrineLoanRepository;
use Phil\LibraryBundle\Infrastructure\Doctrine\DoctrineTitleRepository;
use Phil\LibraryBundle\Infrastructure\Stockroom\NoStockLink;
use Psr\Clock\ClockInterface;

/**
 * An entity manager of the bundle's mapping on the server of a URL, and the services on it, for the integration tests.
 */
final readonly class Server
{
    public function __construct(
        public EntityManager $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public static function connect(string $url, ClockInterface $clock): self
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 2).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $parameters = new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url);

        return new self(new EntityManager(DriverManager::getConnection($parameters), $configuration), $clock);
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

    public function loans(): DoctrineLoanRepository
    {
        return new DoctrineLoanRepository($this->entityManager);
    }

    public function copies(): DoctrineCopyRepository
    {
        return new DoctrineCopyRepository($this->entityManager);
    }

    public function catalogue(): Catalogue
    {
        return new Catalogue(new DoctrineTitleRepository($this->entityManager), $this->copies(), $this->clock);
    }

    public function service(): Loans
    {
        return new Loans($this->copies(), $this->loans(), new NoStockLink(), new LoanPolicy(14, 1, 5, true, 60, 30, 'America/Toronto'), $this->clock);
    }

    public function search(): DbalTitleSearch
    {
        return new DbalTitleSearch($this->entityManager->getConnection(), $this->clock);
    }
}
