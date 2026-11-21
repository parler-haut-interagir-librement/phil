<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Tests\Integration;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceStoredValueBundle\Application\GiftCards;
use Phil\CommerceStoredValueBundle\Application\GiftCardSellables;
use Phil\CommerceStoredValueBundle\Domain\Repository\InstrumentRepository;
use Phil\CommerceStoredValueBundle\Infrastructure\Doctrine\DoctrineStoredValueRepository;
use Phil\CommerceStoredValueBundle\Tests\Unit\Support\InMemoryPayments;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * An entity manager of the module's mapping on the database of a URL — `sqlite:` and a file path, or a server's —,
 * for the integration tests and the processes they start.
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

        $parameters = str_starts_with($url, 'sqlite:')
            ? ['driver' => 'pdo_sqlite', 'path' => substr($url, 7)]
            : new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url);

        return new self(new EntityManager(DriverManager::getConnection($parameters), $configuration));
    }

    /**
     * Drops the module's tables, if they are there, and creates them again from the mapping.
     */
    public function recreate(): void
    {
        $tool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function repository(): DoctrineStoredValueRepository
    {
        return new DoctrineStoredValueRepository($this->entityManager);
    }

    /**
     * Gift cards on this database, or on the view of it a test gives.
     */
    public function giftCards(?InstrumentRepository $instruments = null): GiftCards
    {
        return new GiftCards($instruments ?? $this->repository(), new Payments([], new InMemoryPayments()));
    }

    /**
     * A placed order that sold three gift cards, given for free: nothing is left to pay, and no payment is stored.
     */
    public static function order(string $number): Order
    {
        $order = Order::open($number, Currency::of('CAD'));
        $reference = GiftCardSellables::reference(Money::of(5_000, Currency::of('CAD')));
        $order->add(Sellable::of($reference, 'Gift card CAD 50.00', Money::of(5_000, Currency::of('CAD')), 'exempt'), 3, Money::zero(Currency::of('CAD')));
        $order->place(new DateTimeImmutable('2026-11-20 10:00:00'));

        return $order;
    }
}
