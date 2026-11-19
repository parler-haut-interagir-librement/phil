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
use Phil\CommerceWholesaleBundle\Application\QuantityRules;
use Phil\CommerceWholesaleBundle\Domain\Model\QuantityRule;
use Phil\CommerceWholesaleBundle\Infrastructure\Doctrine\DoctrineWholesaleRepository;
use Phil\CommerceWholesaleBundle\Infrastructure\Validator\AllowedQuantityValidator;
use Phil\CommerceWholesaleBundle\Infrastructure\Validator\QuantityGuard;
use Phil\Money\Currency;
use Phil\Money\Money;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;

/**
 * The quantity guard is asked first for every product line on every change of the cart, and reads the SKU's rules
 * through Symfony's validator each time.
 *
 * @BeforeMethods("setUp")
 */
final class QuantityGuardBench
{
    private readonly EntityManager $entityManager;

    private readonly DoctrineWholesaleRepository $repository;

    private readonly QuantityGuard $guard;

    private readonly PricingContext $context;

    /** @var list<Sellable> */
    private array $cart = [];

    public function __construct()
    {
        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([\dirname(__DIR__, 2).'/src/Domain/Model']));
        $config->enableNativeLazyObjects(true);

        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        new SchemaTool($this->entityManager)->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
        $this->repository = new DoctrineWholesaleRepository($this->entityManager);
        $validator = Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([AllowedQuantityValidator::class => new AllowedQuantityValidator(new QuantityRules($this->repository))]))
            ->getValidator();
        $this->guard = new QuantityGuard($validator);
        $this->context = PricingContext::of(12, new DateTimeImmutable('2026-11-11 12:00:00'), 'wholesale');
    }

    /**
     * @param array{lines: int} $params
     */
    public function setUp(array $params): void
    {
        $currency = Currency::of('CAD');

        for ($i = 0; $i < $params['lines']; ++$i) {
            $sku = 'SKU-'.$i;
            $this->repository->addRule(QuantityRule::of($sku, null, 1, 100));

            if (0 === $i % 2) {
                $this->repository->addRule(QuantityRule::of($sku, 'wholesale', 6, null, 6));
            }

            $this->cart[] = Sellable::of(SellableReference::of('product', $sku), 'Item '.$i, Money::of(1_999, $currency), 'goods');
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * Every line of the cart checked, its rules read from the database.
     *
     * @ParamProviders("carts")
     */
    public function benchCart(): void
    {
        foreach ($this->cart as $sellable) {
            $this->guard->priceFor($sellable, $this->context);
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
