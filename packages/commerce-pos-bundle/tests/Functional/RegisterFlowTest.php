<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommercePosBundle\Application\CashSessions;
use Phil\CommercePosBundle\Application\Layaways;
use Phil\CommercePosBundle\Application\RegisterCheckout;
use Phil\CommercePosBundle\Application\RegisterPayments;
use Phil\CommercePosBundle\Application\Registers;
use Phil\CommercePosBundle\Domain\Model\CashMovement;
use Phil\CommercePosBundle\Domain\Model\HeldSale;
use Phil\CommercePosBundle\Domain\Model\RegisterSale;
use Phil\CommercePosBundle\Domain\Model\SessionPayment;
use Phil\CommercePosBundle\Domain\Repository\CashSessionRepository;
use Phil\CommercePosBundle\Domain\Repository\RegisterSaleRepository;
use Phil\CommercePosBundle\PhilCommercePosBundle;
use Phil\CommercePosBundle\Tests\Functional\App\TestKernel;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversClass(PhilCommercePosBundle::class)]
final class RegisterFlowTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
        $this->entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        new SchemaTool($this->entityManager)->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
    }

    public function testADayAtTheRegister(): void
    {
        $at = new DateTimeImmutable('2026-11-03 09:00:00');
        $this->service(Registers::class, Registers::class)->create('front', 'Front', Currency::of('CAD'), 5);
        $sessions = $this->service(CashSessions::class, CashSessions::class);
        $session = $sessions->open('front', 'ada', $this->cad(10_000), $at);
        $parked = $this->order('A-0');
        $this->service(RegisterCheckout::class, RegisterCheckout::class)->hold($parked, 'front', 'Ada', 'ada', $at);
        $order = $this->order('A-1');
        $layaway = $this->order('A-2');
        $this->entityManager->flush();

        $checkout = $this->service(RegisterCheckout::class, RegisterCheckout::class);
        $checkout->tip($order, $this->cad(100));
        $checkout->ring($order, $session, 'ada', $at);
        $checkout->ring($layaway, $session, 'ada', $at);

        $this->entityManager->flush();
        $payments = $this->service(RegisterPayments::class, RegisterPayments::class);
        $payments->cash($order, $session, $this->cad(2_000), 'ada', $at);
        $payments->recorded($layaway, $session, 'debit', $this->cad(500), 'OK-1', $at);

        $this->entityManager->flush();
        $this->service(Layaways::class, Layaways::class)->open($layaway, 'ada', $at->modify('+30 days'), $at);
        $sessions->drop($session->id, $this->cad(5_000), 'ada', $at);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $sales = $this->service(RegisterSaleRepository::class, RegisterSaleRepository::class);
        self::assertSame(['Ada'], array_map(static fn (HeldSale $held): string => $held->label, $sales->heldAt('front')));
        self::assertSame('ada', $sales->findLayaway('A-2')?->customer);
        self::assertCount(1, $sales->layawaysDue($at->modify('+30 days')));
        self::assertNotNull($this->service(CashSessionRepository::class, CashSessionRepository::class)->openSessionOf('front'));

        $report = $sessions->close($session->id, $this->cad(6_100), 'bob', $at->modify('+8 hours'));
        $this->entityManager->flush();

        self::assertSame([1_100, 5_000, 100, 2], [$report->cashSales->minorUnits, $report->drops->minorUnits, $report->tips->minorUnits, $report->sales]);
        self::assertSame(['cash' => 1_098, 'debit' => 500], array_map(static fn (Money $money): int => $money->minorUnits, $report->byMethod));
        self::assertSame([6_100, 0], [$report->expected()->minorUnits, $report->overShort()?->minorUnits]);
        self::assertNull($this->service(CashSessionRepository::class, CashSessionRepository::class)->openSessionOf('front'));
    }

    /**
     * Each lookup reads the rows of its own register or session only, oldest first however they were written.
     */
    public function testEachLookupReadsItsOwnRowsOldestFirst(): void
    {
        $at = new DateTimeImmutable('2026-11-03 09:00:00');
        $registers = $this->service(Registers::class, Registers::class);
        $registers->create('front', 'Front', Currency::of('CAD'));
        $registers->create('back', 'Back', Currency::of('CAD'));

        $sessions = $this->service(CashSessions::class, CashSessions::class);
        $back = $sessions->open('back', 'bob', $this->cad(1_000), $at);
        $this->entityManager->flush();

        $sessionRepository = $this->service(CashSessionRepository::class, CashSessionRepository::class);
        self::assertNull($sessionRepository->openSessionOf('front'));

        $front = $sessions->open('front', 'ada', $this->cad(10_000), $at);
        $sessions->drop($front->id, $this->cad(3_000), 'ada', $at->modify('+3 hours'));
        $sessions->drop($front->id, $this->cad(1_000), 'ada', $at->modify('+1 hour'));
        $sessions->drop($back->id, $this->cad(500), 'bob', $at->modify('+2 hours'));

        $checkout = $this->service(RegisterCheckout::class, RegisterCheckout::class);
        $later = $this->order('A-1');
        $earlier = $this->order('A-2');
        $elsewhere = $this->order('A-3');
        $this->entityManager->flush();
        $checkout->ring($later, $front, 'ada', $at->modify('+2 hours'));
        $checkout->ring($earlier, $front, 'ada', $at->modify('+1 hour'));
        $checkout->ring($elsewhere, $back, 'bob', $at->modify('+1 hour'));

        $this->entityManager->flush();

        $payments = $this->service(RegisterPayments::class, RegisterPayments::class);
        $payments->recorded($later, $front, 'debit', $this->cad(100), 'OK-LATE', $at->modify('+4 hours'));
        $payments->recorded($earlier, $front, 'debit', $this->cad(100), 'OK-EARLY', $at->modify('+3 hours'));
        $payments->recorded($elsewhere, $back, 'debit', $this->cad(100), 'OK-BACK', $at->modify('+3 hours'));

        $checkout->hold($this->order('H-1'), 'front', 'Second', 'ada', $at->modify('+5 hours'));
        $checkout->hold($this->order('H-2'), 'front', 'First', 'ada', $at->modify('+4 hours'));
        $checkout->hold($this->order('H-3'), 'back', 'Elsewhere', 'bob', $at->modify('+4 hours'));

        $this->entityManager->flush();
        $this->entityManager->clear();

        $sales = $this->service(RegisterSaleRepository::class, RegisterSaleRepository::class);
        self::assertSame($front->id, $this->service(CashSessionRepository::class, CashSessionRepository::class)->openSessionOf('front')?->id);
        self::assertSame([10_000, -1_000, -3_000], array_map(static fn (CashMovement $movement): int => $movement->amount->minorUnits, $sessionRepository->cashMovementsOf($front->id)));
        self::assertSame(['A-2', 'A-1'], array_map(static fn (RegisterSale $sale): string => $sale->orderNumber, $sales->salesOf($front->id)));
        self::assertSame(['OK-EARLY', 'OK-LATE'], array_map(static fn (SessionPayment $payment): ?string => $payment->approval, $sales->paymentsOf($front->id)));
        self::assertSame(['First', 'Second'], array_map(static fn (HeldSale $held): string => $held->label, $sales->heldAt('front')));
    }

    private function order(string $number): Order
    {
        $order = Order::open($number, Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'BOOK-'.$number), 'Book', $this->cad(499), 'standard'), 2, $this->cad(499));
        $this->service(OrderRepository::class, OrderRepository::class)->add($order);

        return $order;
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $id, string $class): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
