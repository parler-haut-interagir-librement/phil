<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\GuestRequest;

use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\GuestRequest\GuestRequest;
use Phil\PrivacyBundle\Domain\GuestRequest\GuestRequestKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GuestRequest::class)]
#[CoversClass(GuestRequestKind::class)]
final class GuestRequestTest extends TestCase
{
    public function testARequestIsIssuedWithASecretOfWhichOnlyTheHashIsKept(): void
    {
        $now = new DateTimeImmutable('2026-11-10T12:00:00Z');
        [$request, $token] = GuestRequest::issue(GuestRequestKind::Erasure, 'a@example.org', $now, $now->modify('+1 hour'));

        self::assertMatchesRegularExpression(GuestRequest::ID_PATTERN, $request->id);
        self::assertMatchesRegularExpression(GuestRequest::TOKEN_PATTERN, $token);
        self::assertSame(hash('sha256', $token), $request->tokenHash);
        self::assertStringNotContainsString($token, serialize($request));
        self::assertSame(GuestRequestKind::Erasure, $request->kind);
        self::assertSame('a@example.org', $request->email);
        self::assertTrue($request->isOpenedBy($token));
        self::assertFalse($request->isOpenedBy(strrev($token)));
        self::assertFalse($request->isOpenedBy(''));
        self::assertFalse($request->isOpenedBy($token.'x'));
        self::assertNotSame($token, GuestRequest::issue(GuestRequestKind::Access, 'a@example.org', $now, $now->modify('+1 hour'))[1]);
    }

    public function testASecretOfTheRightHashButTheWrongShapeIsRefused(): void
    {
        $now = new DateTimeImmutable('2026-11-10T12:00:00Z');
        $request = new GuestRequest(str_repeat('a', 32), GuestRequestKind::Access, 'a@example.org', hash('sha256', 'short'), $now, $now->modify('+1 hour'));

        self::assertFalse($request->isOpenedBy('short'));
    }

    public function testItExpiresAtItsExpiry(): void
    {
        $now = new DateTimeImmutable('2026-11-10T12:00:00Z');
        [$request] = GuestRequest::issue(GuestRequestKind::Access, 'a@example.org', $now, $now->modify('+1 hour'));

        self::assertFalse($request->isExpired($now->modify('+59 minutes 59 seconds')));
        self::assertTrue($request->isExpired($now->modify('+1 hour')));
        self::assertTrue($request->isExpired($now->modify('+2 hours')));
    }

    public function testWhatIsRefused(): void
    {
        $now = new DateTimeImmutable('2026-11-10T12:00:00Z');
        $hash = hash('sha256', 'x');

        foreach ([
            'A request identifier is 32 lowercase hexadecimal characters.' => static fn (): GuestRequest => new GuestRequest(str_repeat('A', 32), GuestRequestKind::Access, 'a@b.c', $hash, $now, $now->modify('+1 hour')),
            'A request keeps the SHA-256 of its secret, in hexadecimal.' => static fn (): GuestRequest => new GuestRequest(str_repeat('a', 32), GuestRequestKind::Access, 'a@b.c', 'x', $now, $now->modify('+1 hour')),
            'A request expires after it is made.' => static fn (): GuestRequest => new GuestRequest(str_repeat('a', 32), GuestRequestKind::Access, 'a@b.c', $hash, $now, $now),
        ] as $message => $build) {
            try {
                $build();
                self::fail($message);
            } catch (InvalidPrivacyValue $invalidPrivacyValue) {
                self::assertSame($message, $invalidPrivacyValue->getMessage());
            }
        }
    }

    public function testTheHashOfASecretIsItsSha256InHexadecimal(): void
    {
        self::assertSame(hash('sha256', 'secret'), GuestRequest::hash('secret'));
    }

    public function testAKeptHashIsExactlySixtyFourHexadecimalCharacters(): void
    {
        $now = new DateTimeImmutable('2026-11-10T12:00:00Z');
        $hash = hash('sha256', 'x');

        foreach (['x'.$hash, $hash.'x', $hash."\n"] as $tokenHash) {
            try {
                new GuestRequest(str_repeat('a', 32), GuestRequestKind::Access, 'a@b.c', $tokenHash, $now, $now->modify('+1 hour'));
                self::fail(json_encode($tokenHash, \JSON_THROW_ON_ERROR));
            } catch (InvalidPrivacyValue $invalidPrivacyValue) {
                self::assertSame('A request keeps the SHA-256 of its secret, in hexadecimal.', $invalidPrivacyValue->getMessage());
            }
        }
    }
}
