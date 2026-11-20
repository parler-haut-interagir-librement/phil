<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Tests\Benchmark;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Generator;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceWholesaleBundle\Application\GroupPrices;
use Phil\CommerceWholesaleBundle\Domain\Model\CustomerGroup;
use Phil\CommerceWholesaleBundle\Infrastructure\Doctrine\DoctrineWholesaleRepository;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * The group price resolver is asked for every product line on every cart recompute, and looks the buyer's group up
 * each time.
 *
 * @BeforeMethods("setUp")
 */
final class GroupPricesBench
{
    private readonly EntityManager $entityManager;

    private readonly GroupPrices $prices;

    /** @var list<Sellable> */
    private array $cart = [];

    private readonly PricingContext $context;

    private readonly DoctrineWholesaleRepository $repository;

    public function __construct()
    {
        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([\dirname(__DIR__, 2).'/src/Domain/Model']));
        $config->enableNativeLazyObjects(true);

        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        new SchemaTool($this->entityManager)->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
        $this->repository = new DoctrineWholesaleRepository($this->entityManager);
        $this->prices = new GroupPrices($this->repository);
        $this->context = PricingContext::of(3, new DateTimeImmutable('2026-11-05 12:00:00'), 'wholesale');
    }

    /**
     * @param array{lines: int} $params
     */
    public function setUp(array $params): void
    {
        $currency = Currency::of('CAD');
        $repository = $this->repository;
        $repository->addGroup(CustomerGroup::of('wholesale', 'Wholesale', $currency, 250_000, Money::of(10_000, $currency)));
        $repository->addGroup(CustomerGroup::of('staff', 'Staff', $currency, 150_000));

        $this->entityManager->flush();
        $this->entityManager->clear();

        for ($i = 0; $i < $params['lines']; ++$i) {
            $this->cart[] = Sellable::of(SellableReference::of('product', 'SKU-'.$i), 'Item '.$i, Money::of(1_999 + $i, $currency), 'goods');
        }

        $this->prices->priceFor($this->cart[0], $this->context);
    }

    /**
     * Every line of the cart priced, the group already in the unit of work.
     *
     * @ParamProviders("carts")
     */
    public function benchCartWarm(): void
    {
        foreach ($this->cart as $sellable) {
            $this->prices->priceFor($sellable, $this->context);
        }
    }

    /**
     * Every line of the cart priced in a fresh unit of work: the first line loads the group.
     *
     * @ParamProviders("carts")
     */
    public function benchCartCold(): void
    {
        $this->entityManager->clear();
        foreach ($this->cart as $sellable) {
            $this->prices->priceFor($sellable, $this->context);
        }
    }

    /**
     * @return Generator<string, array{lines: int}>
     */
    public function carts(): Generator
    {
        yield '5 lines' => ['lines' => 5];
        yield '20 lines' => ['lines' => 20];
        yield '100 lines' => ['lines' => 100];
    }
}
