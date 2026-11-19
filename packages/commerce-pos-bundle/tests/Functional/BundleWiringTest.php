<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Payment\PaymentEvent;
use Phil\CommerceBundle\Domain\Payment\PaymentState;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommercePosBundle\Application\CashDifferences;
use Phil\CommercePosBundle\Application\CashierSwitch;
use Phil\CommercePosBundle\Application\CashSessions;
use Phil\CommercePosBundle\Application\CustomerDisplay;
use Phil\CommercePosBundle\Application\RegisterCheckout;
use Phil\CommercePosBundle\Application\Registers;
use Phil\CommercePosBundle\Application\SalePresets;
use Phil\CommercePosBundle\Application\TerminalPayments;
use Phil\CommercePosBundle\Application\TillDraws;
use Phil\CommercePosBundle\Domain\Exception\CashierPinRefused;
use Phil\CommercePosBundle\Domain\Register\RegisterRole;
use Phil\CommercePosBundle\Infrastructure\Http\TerminalNotificationController;
use Phil\CommercePosBundle\Infrastructure\Terminal\FakePaymentTerminal;
use Phil\CommercePosBundle\PhilCommercePosBundle;
use Phil\CommercePosBundle\Tests\Functional\App\TestKernel;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * The module's services as an application gets them: the terminals, the fake adapter when asked for, the cashier
 * switch off by default and on with a rate limiter, and the configuration refused when it would be unsafe.
 */
#[CoversClass(PhilCommercePosBundle::class)]
final class BundleWiringTest extends TestCase
{
    /** @var list<TestKernel> */
    private array $kernels = [];

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->kernels as $kernel) {
            $kernel->shutdown();
        }
    }

    public function testAChargeOnTheFakeTerminalIsSettledByItsNotification(): void
    {
        $container = $this->boot(['terminals' => ['fake' => true]]);
        $entityManager = $container->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $registers = $this->service($container, Registers::class);
        $registers->create('front', 'Front', Currency::of('CAD'), 5, role: RegisterRole::Main);
        $registers->addTerminal('t1', 'front', FakePaymentTerminal::NAME, 'tmr_1', 'Reader');

        $session = $this->service($container, CashSessions::class)->open('front', 'ada', Money::of(0, Currency::of('CAD')), $at);
        $order = Order::open('A-1', Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'BOOK'), 'Book', Money::of(499, Currency::of('CAD')), 'standard'), 1, Money::of(499, Currency::of('CAD')));

        $orders = $container->get(OrderRepository::class);
        self::assertInstanceOf(OrderRepository::class, $orders);
        $orders->add($order);
        $entityManager->flush();

        $this->service($container, RegisterCheckout::class)->ring($order, $session, 'ada', $at);
        $entityManager->flush();
        $payment = $this->service($container, TerminalPayments::class)->charge($order, $session, 't1', $at, Money::of(1, Currency::of('CAD')));
        $entityManager->flush();

        $fake = $this->service($container, FakePaymentTerminal::class);
        self::assertSame('tmr_1', $fake->presented()[0]->device);
        [$payload, $headers] = $fake->notification('evt-1', $payment->id, PaymentEvent::Captured);
        $request = Request::create('/terminal-notifications', 'POST', server: ['HTTP_X_FAKE_TERMINAL_SIGNATURE' => $headers[FakePaymentTerminal::SIGNATURE_HEADER][0]], content: $payload);

        self::assertSame(200, $this->service($container, TerminalNotificationController::class)($request)->getStatusCode());
        self::assertSame(PaymentState::Completed, $payment->state());
    }

    public function testEveryServiceOfTheCounterIsThere(): void
    {
        $container = $this->boot([]);

        foreach ([SalePresets::class, CustomerDisplay::class, TillDraws::class, CashDifferences::class, TerminalPayments::class, CashierSwitch::class] as $service) {
            self::assertTrue($container->has($service), $service);
        }

        self::assertFalse($container->has(FakePaymentTerminal::class));
        self::assertFalse($this->service($container, CashierSwitch::class)->isEnabled());

        $this->expectException(CashierPinRefused::class);

        $this->service($container, CashierSwitch::class)->switchTo('front', 'ada', '2580', new DateTimeImmutable());
    }

    public function testTheCashierSwitchIsOnWithItsRateLimiter(): void
    {
        $container = $this->boot(['cashier_pin' => ['enabled' => true, 'limiter' => 'test.pin_limiter']]);

        self::assertTrue($this->service($container, CashierSwitch::class)->isEnabled());
    }

    public function testTheCashierSwitchIsNotEnabledWithoutARateLimiter(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The cashier switch by PIN names the rate limiter that bounds its attempts: set cashier_pin.limiter.');

        $this->boot(['cashier_pin' => ['enabled' => true]]);
    }

    public function testTheTerminalsMethodIsAMethodOtherThanCash(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains("The terminals' method is a payment method");

        $this->boot(['terminals' => ['method' => 'cash']]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function boot(array $config): ContainerInterface
    {
        $kernel = new TestKernel('test', true, pos: $config);
        $this->kernels[] = $kernel;
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(ContainerInterface $container, string $class): object
    {
        $service = $container->get($class);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
