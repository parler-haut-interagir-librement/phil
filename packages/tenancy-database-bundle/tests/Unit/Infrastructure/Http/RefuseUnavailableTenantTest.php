<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Infrastructure\Http;

use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Domain\Exception\TenantDatabaseUnavailable;
use Phil\TenancyDatabaseBundle\Infrastructure\Availability\TenantAvailabilityGuard;
use Phil\TenancyDatabaseBundle\Infrastructure\Http\RefuseUnavailableTenant;
use Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\FixedPlacements;
use Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\MemoryAvailability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(RefuseUnavailableTenant::class)]
final class RefuseUnavailableTenantTest extends TestCase
{
    private TenantContext $context;

    private RefuseUnavailableTenant $listener;

    protected function setUp(): void
    {
        $this->context = new TenantContext();
        $availability = new MemoryAvailability();
        $availability->suspendTenant(TenantId::fromString('retail'));

        $this->listener = new RefuseUnavailableTenant($this->context, new TenantAvailabilityGuard(new FixedPlacements(), $availability), 45);
    }

    public function testARequestInASuspendedTenantIsAnsweredWithA503(): void
    {
        $this->context->enter(TenantId::fromString('retail'));

        try {
            $this->listener->onRequest($this->event(HttpKernelInterface::MAIN_REQUEST));
            self::fail('The request was not refused.');
        } catch (ServiceUnavailableHttpException $serviceUnavailableHttpException) {
            self::assertSame(503, $serviceUnavailableHttpException->getStatusCode());
            self::assertSame(['Retry-After' => 45], $serviceUnavailableHttpException->getHeaders());
            self::assertSame('This site is under maintenance; try again shortly.', $serviceUnavailableHttpException->getMessage());
            self::assertInstanceOf(TenantDatabaseUnavailable::class, $serviceUnavailableHttpException->getPrevious());
        }
    }

    public function testARequestInAnAvailableTenantGoesOn(): void
    {
        $this->context->enter(TenantId::fromString('wholesale'));
        $event = $this->event(HttpKernelInterface::MAIN_REQUEST);

        $this->listener->onRequest($event);

        self::assertFalse($event->hasResponse());
    }

    public function testARequestInNoTenantGoesOn(): void
    {
        $event = $this->event(HttpKernelInterface::MAIN_REQUEST);

        $this->listener->onRequest($event);

        self::assertFalse($event->hasResponse());
    }

    public function testASubRequestIsNotCheckedAgain(): void
    {
        $this->context->enter(TenantId::fromString('retail'));
        $event = $this->event(HttpKernelInterface::SUB_REQUEST);

        $this->listener->onRequest($event);

        self::assertFalse($event->hasResponse());
    }

    private function event(int $type): RequestEvent
    {
        return new RequestEvent(self::createStub(HttpKernelInterface::class), Request::create('/'), $type);
    }
}
