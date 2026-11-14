<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Functional;

use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Messenger\TenantMiddleware;
use Phil\TenancyBundle\Infrastructure\Messenger\TenantStamp;
use Phil\TenancyBundle\Tests\Functional\App\CountInvoices;
use Phil\TenancyBundle\Tests\Functional\App\CountInvoicesHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * A message dispatched in a tenant, sent to a transport, then received by a worker that acts in no tenant: it
 * is handled in the tenant it was dispatched in, and the worker returns to none.
 */
#[CoversClass(TenantMiddleware::class)]
#[CoversClass(TenantStamp::class)]
final class MessengerTest extends FunctionalTestCase
{
    public function testAMessageIsHandledInTheTenantItWasDispatchedIn(): void
    {
        $context = $this->service(TenantContext::class, TenantContext::class);
        $context->within(TenantId::fromString('retail'), fn (): Envelope => $this->bus()->dispatch(new CountInvoices()));
        $this->bus()->dispatch(new CountInvoices());

        [$inRetail, $inNone] = array_values([...$this->transport()->getSent()]);
        self::assertSame('retail', $inRetail->last(TenantStamp::class)?->tenant);
        self::assertNull($inNone->last(TenantStamp::class));

        foreach ([$inRetail, $inNone] as $envelope) {
            $this->bus()->dispatch($envelope->with(new ReceivedStamp('async')));
        }

        self::assertSame([['retail', 2], [null, 0]], $this->service(CountInvoicesHandler::class, CountInvoicesHandler::class)->handled);
        self::assertNull($context->current());
    }

    private function bus(): MessageBusInterface
    {
        return $this->service('messenger.bus.default', MessageBusInterface::class);
    }

    private function transport(): InMemoryTransport
    {
        return $this->service('messenger.transport.async', InMemoryTransport::class);
    }
}
