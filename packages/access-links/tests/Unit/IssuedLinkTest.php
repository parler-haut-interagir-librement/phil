<?php

declare(strict_types=1);

namespace Phil\AccessLinks\Tests\Unit;

use DateTimeImmutable;
use LogicException;
use Phil\AccessLinks\Invitation;
use Phil\AccessLinks\IssuedLink;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IssuedLink::class)]
final class IssuedLinkTest extends TestCase
{
    public function testTheTokenAndTheAddressNeverShowInADump(): void
    {
        $link = $this->link();

        self::assertSame('tok', $link->token);
        self::assertSame('https://events.example/rsvp?token=tok', $link->url->toString());
        self::assertSame(['invitation' => '0123456789abcdef0123456789abcdef', 'token' => 'hidden', 'url' => 'hidden'], $link->__debugInfo());
    }

    public function testItIsNeverSerialized(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('An issued link holds its token in clear: it is never serialized.');

        serialize($this->link());
    }

    private function link(): IssuedLink
    {
        $invitation = new Invitation('0123456789abcdef0123456789abcdef', 'c', 'r', new DateTimeImmutable('2026-11-13 10:00'), new DateTimeImmutable('2026-11-14 10:00'));

        return new IssuedLink($invitation, 'tok', Url::fromString('https://events.example/rsvp?token=tok'));
    }
}
