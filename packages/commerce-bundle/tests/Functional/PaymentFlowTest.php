<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Payment\OrderPaymentState;
use Phil\CommerceBundle\Domain\Payment\PaymentState;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceBundle\Domain\Repository\PaymentRepository;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrinePaymentRepository;
use Phil\CommerceBundle\Infrastructure\Http\PaymentWebhookController;
use Phil\CommerceBundle\PhilCommerceBundle;
use Phil\CommerceBundle\Tests\Functional\App\TestGateway;
use Phil\Money\Currency;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Request;

/**
 * A placed order paid through the gateway the application plugs in: the payment is stored, the processor's signed
 * notification reaches the webhook controller, and the payment and the order read back paid from a fresh unit of
 * work.
 */
#[CoversClass(PhilCommerceBundle::class)]
#[CoversClass(DoctrinePaymentRepository::class)]
#[CoversClass(PaymentWebhookController::class)]
final class PaymentFlowTest extends FunctionalTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        $this->createSchema($this->entityManager);
    }

    public function testAPaymentIsConfirmedByASignedNotification(): void
    {
        $order = $this->placedOrder('2026-0001');
        $payments = $this->service(Payments::class, Payments::class);

        [$payment, $start] = $payments->start($order, 'card', new DateTimeImmutable('2026-11-03T12:10:00+00:00'));
        $this->entityManager->flush();

        self::assertSame('secret_'.$payment->id, $start->clientSecret);

        $payload = json_encode(['id' => 'evt_1', 'payment' => 'pi_'.$payment->id, 'type' => 'captured'], \JSON_THROW_ON_ERROR);
        $controller = $this->service(PaymentWebhookController::class, PaymentWebhookController::class);
        $response = $controller(Request::create('/commerce/webhooks/card', 'POST', server: ['HTTP_X_TEST_SIGNATURE' => hash_hmac('sha256', $payload, TestGateway::SECRET)], content: $payload), 'card');

        self::assertSame(204, $response->getStatusCode());

        $this->entityManager->clear();
        $repository = $this->service(PaymentRepository::class, PaymentRepository::class);
        $stored = $repository->forOrder('2026-0001');

        self::assertCount(1, $stored);
        self::assertSame($payment->id, $stored[0]->id);
        self::assertSame(PaymentState::Completed, $stored[0]->state());
        self::assertSame('pi_'.$payment->id, $stored[0]->externalReference());
        self::assertSame('2026-11-03T12:10:00+00:00', $stored[0]->createdAt->format(\DATE_ATOM));
        self::assertSame(2, $stored[0]->version());
        self::assertSame($payment->id, $repository->find($payment->id)?->id);
        self::assertSame($payment->id, $repository->findByExternalReference('card', 'pi_'.$payment->id)?->id);
        self::assertNull($repository->findByExternalReference('interac', 'pi_'.$payment->id));
        self::assertSame([], $repository->forOrder('2026-0002'));

        $reloaded = $this->service(OrderRepository::class, OrderRepository::class)->find('2026-0001');
        self::assertNotNull($reloaded);
        self::assertSame(OrderPaymentState::Paid, $payments->summary($reloaded)->state());

        $replay = $controller(Request::create('/commerce/webhooks/card', 'POST', server: ['HTTP_X_TEST_SIGNATURE' => hash_hmac('sha256', $payload, TestGateway::SECRET)], content: $payload), 'card');
        self::assertSame(204, $replay->getStatusCode());
    }

    /**
     * Payments of one order come back oldest first.
     */
    public function testTheOrdersPaymentsComeBackOldestFirst(): void
    {
        $order = $this->placedOrder('2026-0003');
        $payments = $this->service(Payments::class, Payments::class);
        [$second] = $payments->start($order, 'card', new DateTimeImmutable('2026-11-03T12:20:00Z'), $order->total()->allocate(1, 1)[0]);
        [$first] = $payments->start($order, 'card', new DateTimeImmutable('2026-11-03T12:10:00Z'));
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame([$first->id, $second->id], array_map(static fn (Payment $payment): string => $payment->id, $this->service(PaymentRepository::class, PaymentRepository::class)->forOrder('2026-0003')));
    }

    /**
     * Another process started a payment of the same order and committed first: the start made over the stale copy
     * of the order is refused at commit, and its payment is not stored beside the first.
     */
    public function testTwoStartsOverOneCopyOfTheOrderCannotBothBeCommitted(): void
    {
        $order = $this->placedOrder('2026-0003');
        $payments = $this->service(Payments::class, Payments::class);

        // What the other process's commit leaves: the order's version moved on.
        $this->entityManager->getConnection()->executeStatement('UPDATE phil_commerce_order SET version = version + 1, payments_started = payments_started + 1 WHERE number = ?', ['2026-0003']);

        $payments->start($order, 'card', new DateTimeImmutable());

        try {
            $this->entityManager->flush();
            self::fail('The stale start was committed.');
        } catch (OptimisticLockException) {
        }

        $this->entityManager->clear();
        self::assertSame([], $this->service(PaymentRepository::class, PaymentRepository::class)->forOrder('2026-0003'));
    }

    private function placedOrder(string $number): Order
    {
        $modifier = $this->service(OrderModifier::class, OrderModifier::class);
        $order = Order::open($number, Currency::of('CAD'));
        $modifier->add($order, SellableReference::of('product', 'dice'), 1, PricingContext::of(1, new DateTimeImmutable()));
        $modifier->place($order, new DateTimeImmutable('2026-11-03T12:05:00Z'));
        $this->service(OrderRepository::class, OrderRepository::class)->add($order);
        $this->entityManager->flush();

        return $order;
    }
}
