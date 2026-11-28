<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Payment\PaymentState;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Infrastructure\Http\PaymentWebhookController;
use Phil\CommerceBundle\Tests\Unit\Support\FakeGateway;
use Phil\CommerceBundle\Tests\Unit\Support\InMemoryPayments;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(PaymentWebhookController::class)]
final class PaymentWebhookControllerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, int, int}>
     */
    public static function answers(): iterable
    {
        yield 'applied' => ['card', 'evt_1|ref-1|captured', 'ok', 204, 1];
        yield 'refused by the state' => ['card', 'evt_1|ref-1|dispute_won', 'ok', 204, 1];
        yield 'forged' => ['card', 'evt_1|ref-1|captured', 'forged', 400, 0];
        yield 'unreadable' => ['card', 'evt_1|ref-1|paid', 'ok', 400, 0];
        yield 'unknown payment' => ['card', 'evt_1|ref-9|captured', 'ok', 404, 0];
        yield 'unknown method' => ['paypal', 'evt_1|ref-1|captured', 'ok', 404, 0];
    }

    #[DataProvider('answers')]
    public function testEachOutcomeHasItsAnswer(string $method, string $payload, string $signature, int $status, int $flushes): void
    {
        [$controller, $payments, $order] = $this->controller($flushes);
        $request = Request::create('/webhooks/'.$method, 'POST', server: ['HTTP_X_SIGNATURE' => $signature], content: $payload);

        $response = $controller($request, $method);

        self::assertSame($status, $response->getStatusCode());
        self::assertSame('', $response->getContent());
        self::assertSame(204 === $status && str_contains($payload, 'captured') ? PaymentState::Completed : PaymentState::Processing, $payments->forOrder($order->number)[0]->state());
    }

    /**
     * A replay is acknowledged too, and committed like any other, harmlessly.
     */
    public function testAReplayIsAcknowledged(): void
    {
        [$controller] = $this->controller(2);
        $request = static fn (): Request => Request::create('/webhooks/card', 'POST', server: ['HTTP_X_SIGNATURE' => 'ok'], content: 'evt_1|ref-1|captured');

        self::assertSame(204, $controller($request(), 'card')->getStatusCode());
        self::assertSame(204, $controller($request(), 'card')->getStatusCode());
    }

    /**
     * A verified event the store does not act on is acknowledged without a commit, and logged by its id and type.
     */
    public function testAnIgnoredNotificationIsAcknowledgedAndLogged(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with(
            'Payment notification {id} of type {type} ignored: the store does not act on it.',
            ['id' => 'evt_9', 'type' => 'customer.created', 'method' => 'card'],
        );
        [$controller, $payments, $order] = $this->controller(0, logger: $logger);
        $request = Request::create('/webhooks/card', 'POST', server: ['HTTP_X_SIGNATURE' => 'ok'], content: 'evt_9|ignored|customer.created');

        $response = $controller($request, 'card');

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', $response->getContent());
        self::assertSame(PaymentState::Processing, $payments->forOrder($order->number)[0]->state());
    }

    public function testAnIgnoredNotificationNeedsNoLogger(): void
    {
        [$controller] = $this->controller(0);
        $request = Request::create('/webhooks/card', 'POST', server: ['HTTP_X_SIGNATURE' => 'ok'], content: 'evt_9|ignored|customer.created');

        self::assertSame(204, $controller($request, 'card')->getStatusCode());
    }

    public function testABodyPastTheLimitIsReadToOneBytePastItAndNoFurther(): void
    {
        [$controller] = $this->controller(0);
        $stream = fopen('php://temp', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, str_repeat(' ', PaymentWebhookController::MAX_BYTES + 4096));
        rewind($stream);
        $request = Request::create('/webhooks/card', 'POST', server: ['HTTP_X_SIGNATURE' => 'ok'], content: $stream);
        $request->headers->remove('Content-Length');

        self::assertSame(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, $controller($request, 'card')->getStatusCode());
        self::assertSame(PaymentWebhookController::MAX_BYTES + 1, ftell($stream));
    }

    public function testABodyOfTheLongestLengthReachesTheGateway(): void
    {
        $gateway = new FakeGateway('card');
        [$controller] = $this->controller(0, $gateway);

        self::assertSame(Response::HTTP_BAD_REQUEST, $controller(Request::create('/webhooks/card', 'POST', server: ['HTTP_X_SIGNATURE' => 'ok'], content: str_repeat(' ', PaymentWebhookController::MAX_BYTES)), 'card')->getStatusCode());
        self::assertSame(['ok'], $gateway->headers['x-signature']);
    }

    /**
     * The gateway receives every header as a list of strings, under its lowercase name.
     */
    public function testTheGatewayReceivesTheHeadersAsListsOfStrings(): void
    {
        $gateway = new FakeGateway('card');
        [$controller] = $this->controller(1, $gateway);
        $request = Request::create('/webhooks/card', 'POST', server: ['HTTP_X_SIGNATURE' => 'ok'], content: 'evt_1|ref-1|captured');
        $request->headers->set('X-Mixed', null);
        $request->headers->set('X-Mixed', 'a', false);

        $controller($request, 'card');

        self::assertSame(['ok'], $gateway->headers['x-signature']);
        self::assertSame(['a'], $gateway->headers['x-mixed']);
    }

    /**
     * @return array{PaymentWebhookController, InMemoryPayments, Order}
     */
    private function controller(int $flushes, FakeGateway $gateway = new FakeGateway('card'), ?LoggerInterface $logger = null): array
    {
        $repository = new InMemoryPayments();
        $payments = new Payments([$gateway], $repository);
        $order = Order::open('1', Currency::of('CAD'));
        $cad = Money::of(1_000, Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'dice'), 'Dice', $cad, 'goods'), 1, $cad);
        $order->place(new DateTimeImmutable());

        $payments->start($order, 'card', new DateTimeImmutable());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->exactly($flushes))->method('flush');

        return [new PaymentWebhookController($payments, $entityManager, $logger), $repository, $order];
    }
}
