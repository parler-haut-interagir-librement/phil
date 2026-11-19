<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Tests\Benchmark;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Generator;
use Phil\CommercePosBundle\Application\CashSessions;
use Phil\CommercePosBundle\Application\Registers;
use Phil\CommercePosBundle\Domain\Model\CashSession;
use Phil\CommercePosBundle\Domain\Model\Register;
use Phil\CommercePosBundle\Domain\Model\RegisterSale;
use Phil\CommercePosBundle\Domain\Model\SessionPayment;
use Phil\CommercePosBundle\Domain\Session\CashMovementKind;
use Phil\CommercePosBundle\Infrastructure\Doctrine\DoctrinePosRepository;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * The end-of-day report reads every cash movement, payment and sale of a session; a busy register at a fair rings up
 * hundreds of sales, and the report is printed at each close and on demand during the day.
 *
 * @BeforeMethods("setUp")
 */
final class CashSessionReportBench
{
    private readonly EntityManager $entityManager;

    private readonly CashSessions $sessions;

    private string $session = '';

    private readonly DoctrinePosRepository $repository;

    public function __construct()
    {
        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([\dirname(__DIR__, 2).'/src/Domain/Model']));
        $config->enableNativeLazyObjects(true);

        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        new SchemaTool($this->entityManager)->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
        $this->repository = new DoctrinePosRepository($this->entityManager);
        $this->sessions = new CashSessions(new Registers($this->repository), $this->repository, $this->repository);
    }

    /**
     * @param array{sales: int} $params
     */
    public function setUp(array $params): void
    {
        $repository = $this->repository;
        $currency = Currency::of('CAD');
        $at = new DateTimeImmutable('2026-11-05 09:00:00');
        $register = Register::of('front', 'Front', $currency, 5);
        $repository->addRegister($register);

        [$session, $float] = CashSession::open($register, 'clerk', Money::of(20_000, $currency), $at);
        $repository->addSession($session);
        $repository->addCashMovement($float);

        $methods = ['cash', 'debit', 'credit', 'gift_card'];

        for ($i = 0; $i < $params['sales']; ++$i) {
            $rungAt = $at->modify(\sprintf('+%d seconds', 30 * $i));
            $amount = Money::of(1_000 + 37 * $i, $currency);
            $method = $methods[$i % 4];
            $repository->addSale(RegisterSale::of('S-'.$i, $session, 'clerk', $rungAt, Money::of($i % 5 * 100, $currency)));
            $repository->addPayment(SessionPayment::of('p-'.$i, $session, 'S-'.$i, $method, $amount, 'cash' === $method ? null : 'APPROVED-'.$i, $rungAt));

            if ('cash' === $method) {
                $repository->addCashMovement($session->move(CashMovementKind::Sale, $amount, Money::of(-2, $currency), 'clerk', $rungAt, 'p-'.$i));
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->session = $session->id;
    }

    /**
     * The report of a session read in a fresh unit of work, as a close or a reprint does.
     *
     * @ParamProviders("days")
     *
     * @Revs(5)
     */
    public function benchReport(): void
    {
        $this->entityManager->clear();
        $this->sessions->report($this->session);
    }

    /**
     * @return Generator<string, array{sales: int}>
     */
    public function days(): Generator
    {
        yield '20 sales' => ['sales' => 20];
        yield '200 sales' => ['sales' => 200];
        yield '800 sales' => ['sales' => 800];
    }
}
