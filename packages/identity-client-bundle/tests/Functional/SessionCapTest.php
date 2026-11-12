<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Functional;

use Phil\IdentityClientBundle\Application\Event\LocalSessionEnded;
use Phil\IdentityClientBundle\Application\Event\LocalSessionEndReason;
use Phil\IdentityClientBundle\Infrastructure\Security\SessionCapListener;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(SessionCapListener::class)]
final class SessionCapTest extends FunctionalTestCase
{
    public function testASessionEndsTwelveHoursAfterItStarted(): void
    {
        $this->boot();
        $this->signIn();

        $this->clock()->modify('+11 hours 59 minutes');
        self::assertSame(Response::HTTP_OK, $this->get('/private')->getStatusCode());

        $this->clock()->modify('+1 minute');
        self::assertTrue($this->get('/private')->isRedirection());

        $ended = $this->events()->of(LocalSessionEnded::class);
        self::assertCount(1, $ended);
        self::assertSame(LocalSessionEndReason::LifetimeExceeded, $ended[0]->reason);
    }

    public function testTheCapCountsFromTheStartOfTheSessionAtTheProvider(): void
    {
        $this->boot();
        $this->signIn(['session_start' => $this->clock()->now()->getTimestamp() - 43_000]);

        $this->clock()->modify('+199 seconds');
        self::assertSame(Response::HTTP_OK, $this->get('/private')->getStatusCode());

        $this->clock()->modify('+1 second');
        self::assertTrue($this->get('/private')->isRedirection());
    }

    public function testTheCapCanBeTurnedOff(): void
    {
        $this->boot(['session' => ['max_lifetime' => null]]);
        $this->signIn();

        $this->clock()->modify('+13 hours');

        self::assertSame(Response::HTTP_OK, $this->get('/private')->getStatusCode());
        self::assertFalse($this->container()->has(SessionCapListener::class));
    }
}
