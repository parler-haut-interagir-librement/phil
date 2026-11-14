<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Infrastructure\Messenger;

use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Domain\Exception\TenantDatabaseUnavailable;
use Phil\TenancyDatabaseBundle\Infrastructure\Availability\TenantAvailabilityGuard;
use Phil\TenancyDatabaseBundle\Infrastructure\Messenger\RefuseUnavailableTenantMiddleware;
use Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\FixedPlacements;
use Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\MemoryAvailability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

#[CoversClass(RefuseUnavailableTenantMiddleware::class)]
final class RefuseUnavailableTenantMiddlewareTest extends TestCase
{
    private TenantContext $context;

    private RefuseUnavailableTenantMiddleware $middleware;

    protected function setUp(): void
    {
        $this->context = new TenantContext();
        $availability = new MemoryAvailability();
        $availability->suspendTenant(TenantId::fromString('retail'));

        $this->middleware = new RefuseUnavailableTenantMiddleware($this->context, new TenantAvailabilityGuard(new FixedPlacements(), $availability));
    }

    public function testAReceivedMessageOfASuspendedTenantWaits(): void
    {
        $this->context->enter(TenantId::fromString('retail'));

        $this->expectException(TenantDatabaseUnavailable::class);

        $this->middleware->handle(new Envelope(new stdClass(), [new ReceivedStamp('async')]), new StackMiddleware());
    }

    public function testAReceivedMessageOfAnAvailableTenantIsHandled(): void
    {
        $this->context->enter(TenantId::fromString('wholesale'));
        $envelope = new Envelope(new stdClass(), [new ReceivedStamp('async')]);

        self::assertSame($envelope, $this->middleware->handle($envelope, new StackMiddleware()));
    }

    public function testAMessageDispatchedInASuspendedTenantIsSent(): void
    {
        $this->context->enter(TenantId::fromString('retail'));
        $envelope = new Envelope(new stdClass());

        self::assertSame($envelope, $this->middleware->handle($envelope, new StackMiddleware()));
    }

    public function testAReceivedMessageInNoTenantIsHandled(): void
    {
        $envelope = new Envelope(new stdClass(), [new ReceivedStamp('async')]);

        self::assertSame($envelope, $this->middleware->handle($envelope, new StackMiddleware()));
    }
}
