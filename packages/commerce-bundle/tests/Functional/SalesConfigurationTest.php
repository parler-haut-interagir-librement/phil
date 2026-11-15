<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Functional;

use Phil\CommerceBundle\Application\OrderLifecycle;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\PhilCommerceBundle;
use Phil\CommerceBundle\Tests\Functional\App\TestKernel;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Exception\InvalidAmount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Throwable;

/**
 * The keys of the gift wrapping, offline payments, payment links, expiry, refunds, statuses and document types: their
 * defaults, and the values that fail the build.
 */
#[CoversClass(PhilCommerceBundle::class)]
final class SalesConfigurationTest extends TestCase
{
    public function testTheDefaultsLeaveNothingToDecide(): void
    {
        FunctionalTestCase::skipWithoutEventDispatcher();
        $kernel = new TestKernel('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();

        $payments = $container->get(Payments::class);
        self::assertInstanceOf(Payments::class, $payments);
        self::assertSame([], $payments->offlineMethods());
        $lifecycle = $container->get(OrderLifecycle::class);
        self::assertInstanceOf(OrderLifecycle::class, $lifecycle);
        self::assertSame([], $lifecycle->statuses());

        $kernel->shutdown();
    }

    /**
     * @return iterable<string, array{array<string, mixed>, class-string<Throwable>, string}>
     */
    public static function refused(): iterable
    {
        yield 'a link lasting longer by default than at most' => [['payment_links' => ['default_hours' => 100, 'max_hours' => 99]], InvalidConfigurationException::class, 'default_hours exceeds max_hours'];
        yield 'an offline method with a space' => [['offline_payments' => ['bank transfer']], InvalidConfigurationException::class, 'An offline payment method is up to 32 lowercase letters'];
        yield 'a status of no state' => [['order_statuses' => ['shipped' => 'sent']], InvalidConfigurationException::class, 'shipped'];
        yield 'a wrapping price that is no amount' => [['gift_wrapping' => ['enabled' => true, 'prices' => ['CAD' => 'four']]], InvalidAmount::class, 'four'];
        yield 'a document type sharing a prefix' => [['document_types' => ['packing_slip' => ['prefix' => 'R-']]], InvalidValue::class, 'share the prefix "R-"'];
        yield 'an expiry of no hour' => [['order_expiry' => ['unpaid_hours' => 0]], InvalidConfigurationException::class, 'unpaid_hours'];
    }

    /**
     * @param array<string, mixed>    $config
     * @param class-string<Throwable> $exception
     */
    #[DataProvider('refused')]
    public function testAValueTheBundleCannotUseFailsTheBuild(array $config, string $exception, string $message): void
    {
        FunctionalTestCase::skipWithoutEventDispatcher();
        $kernel = new TestKernel('test', true, TestKernel::RATES + $config);

        $this->expectException($exception);
        $this->expectExceptionMessageIsOrContains($message);

        $kernel->boot();
    }
}
