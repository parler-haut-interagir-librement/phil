<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Integration;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Ergebnis\PHPUnit\SlowTestDetector\Attribute\MaximumDuration;
use Override;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Model\Refund;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Two clerks grant a refund of the same payment of 40.00 CAD at once, on a real server read from the environment: the
 * second one, in a process of its own, waits for the first one's lock, then counts what the first one granted.
 */
abstract class ServerRefundLockTestCase extends TestCase
{
    private ?EntityManager $entityManager = null;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    /**
     * @return iterable<string, array{int, int, string, int}>
     */
    public static function grants(): iterable
    {
        yield 'together more than the payment' => [3_000, 2_000, 'refused', 3_000];
        yield 'together at most the payment' => [1_000, 3_000, 'granted', 4_000];
    }

    #[DataProvider('grants')]
    #[MaximumDuration(5_000)]
    public function testTheSecondGrantWaitsForTheFirstAndCountsIt(int $first, int $second, string $outcome, int $granted): void
    {
        $url = $this->url();
        $entityManager = RefundLockStage::entityManager($url);
        $this->entityManager = $entityManager;
        $paymentId = $this->paidPayment($entityManager);

        $entityManager->beginTransaction();
        $payment = $entityManager->find(Payment::class, $paymentId);
        self::assertInstanceOf(Payment::class, $payment);
        RefundLockStage::refunds($entityManager)->grant($payment, Money::of($first, Currency::of('CAD')), 'First clerk', 'clerk-1', new DateTimeImmutable('2026-11-20 10:00:00'));
        $entityManager->flush();

        $php = new PhpExecutableFinder()->find(false);
        self::assertIsString($php);
        $clerk = new Process([$php, __DIR__.'/grant-refund.php', $url, $paymentId, (string) $second]);
        $clerk->start();
        $clerk->waitUntil(static fn (string $type, string $output): bool => str_contains($output, 'ready'));

        // The second clerk has read the payment and asked for its lock: it waits while the first one holds it.
        usleep(400_000);
        self::assertTrue($clerk->isRunning(), 'The second grant waits for the lock of the first one. '.$clerk->getErrorOutput());

        $entityManager->commit();
        $clerk->wait();

        self::assertTrue($clerk->isSuccessful(), $clerk->getErrorOutput());
        self::assertSame("ready\n".$outcome, trim($clerk->getOutput()));
        $sum = $entityManager->getConnection()->fetchOne('SELECT SUM(amount) FROM phil_commerce_refund WHERE payment_id = ?', [$paymentId]);
        self::assertIsNumeric($sum);
        self::assertSame($granted, (int) $sum);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->entityManager?->getConnection()->close();
        $this->entityManager = null;
    }

    private function url(): string
    {
        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        return $url;
    }

    /**
     * The tables of the payments and the refunds, created afresh — only those, since the server is shared with the tests
     * of other packages —, and a payment of 40.00 CAD received.
     */
    private function paidPayment(EntityManager $entityManager): string
    {
        $schema = new SchemaTool($entityManager)->getSchemaFromMetadata([$entityManager->getClassMetadata(Payment::class), $entityManager->getClassMetadata(Refund::class)]);
        $connection = $entityManager->getConnection();
        $manager = $connection->createSchemaManager();

        foreach ($schema->getTables() as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table->getObjectName()->toString());
        }

        foreach ($schema->getTables() as $table) {
            $manager->createTable($table);
        }

        $payment = Payment::declareOffline(Order::open('2026-0001', Currency::of('CAD')), 'cheque', Money::of(4_000, Currency::of('CAD')), new DateTimeImmutable('2026-11-20 09:00:00'));
        $payment->confirmReceived();

        $entityManager->persist($payment);
        $entityManager->flush();
        $entityManager->clear();

        return $payment->id;
    }
}
