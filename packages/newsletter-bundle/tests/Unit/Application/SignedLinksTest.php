<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Application;

use Phil\NewsletterBundle\Application\Exception\LinkRefused;
use Phil\NewsletterBundle\Application\SignedLink;
use Phil\NewsletterBundle\Application\SignedLinks;
use Phil\NewsletterBundle\Domain\Enum\LinkPurpose;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(SignedLinks::class)]
#[CoversClass(SignedLink::class)]
#[CoversClass(LinkRefused::class)]
final class SignedLinksTest extends TestCase
{
    private MockClock $clock;

    private SignedLinks $links;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-13 10:00:00 UTC');
        $this->links = new SignedLinks('a-key-of-sixteen-bytes-at-least', $this->clock);
    }

    public function testALinkSaysWhatItWasSignedFor(): void
    {
        $token = $this->links->sign(LinkPurpose::Unsubscribe, 'subscriber-1', 'list-2');
        $link = $this->links->verify($token, LinkPurpose::Unsubscribe);

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]{22}$/', $token);
        self::assertSame(LinkPurpose::Unsubscribe, $link->purpose);
        self::assertSame('subscriber-1', $link->subject);
        self::assertSame('list-2', $link->extra);
        self::assertSame($this->clock->now()->getTimestamp(), $link->issuedAt);
        self::assertNull($link->expiresAt);
        self::assertSame('', $this->links->verify($this->links->sign(LinkPurpose::Click, 'c'), LinkPurpose::Click)->extra);
    }

    public function testALinkExpires(): void
    {
        $token = $this->links->sign(LinkPurpose::Confirm, 's', 'l', 60);
        self::assertSame($this->clock->now()->getTimestamp() + 60, $this->links->verify($token, LinkPurpose::Confirm)->expiresAt);

        $this->clock->modify('+59 seconds');
        $this->links->verify($token, LinkPurpose::Confirm);
        $this->clock->modify('+1 second');

        $this->expectException(LinkRefused::class);
        $this->expectExceptionMessageIsOrContains('The link has expired.');
        $this->links->verify($token, LinkPurpose::Confirm);
    }

    #[DataProvider('refused')]
    public function testItRefusesWhatItDidNotSign(string $token, string $message): void
    {
        $this->expectException(LinkRefused::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->links->verify($token, LinkPurpose::Unsubscribe);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refused(): iterable
    {
        $links = new SignedLinks('a-key-of-sixteen-bytes-at-least', new MockClock('2026-11-13 10:00:00 UTC'));
        $other = new SignedLinks('another-key-of-sixteen-bytes', new MockClock('2026-11-13 10:00:00 UTC'));
        $token = $links->sign(LinkPurpose::Unsubscribe, 's');
        [$payload, $mac] = explode('.', $token);

        yield 'empty' => ['', 'The link is malformed.'];
        yield 'no signature' => [$payload, 'The link is malformed.'];
        yield 'a short signature' => [$payload.'.'.substr($mac, 1), 'The link is malformed.'];
        yield 'too long' => [str_repeat('a', 490).'.'.$mac, 'The link is malformed.'];
        yield 'a trailing new line' => [$token."\n", 'The link is malformed.'];
        yield 'another key' => [$other->sign(LinkPurpose::Unsubscribe, 's'), 'The link is not signed by the newsletter.'];
        yield 'a changed payload' => [strrev($payload).'.'.$mac, 'The link is not signed by the newsletter.'];
        yield 'not base64' => ['a.'.$mac, 'The link is not signed by the newsletter.'];
        yield 'another purpose' => [$links->sign(LinkPurpose::Preferences, 's'), 'The link is for something else.'];
        yield 'too few fields' => [self::forged('unsubscribe|s|x|1'), 'The link is for something else.'];
        yield 'a bad issue time' => [self::forged('unsubscribe|s||x|'), 'The link is for something else.'];
        yield 'a bad expiry' => [self::forged('unsubscribe|s||1|x'), 'The link is for something else.'];
    }

    public function testAFieldCannotHoldTheSeparator(): void
    {
        $this->expectException(LinkRefused::class);
        $this->expectExceptionMessageIsOrContains('A field of a link holds no "|".');

        $this->links->sign(LinkPurpose::Click, 'a|b');
    }

    public function testTheKeyIsLongEnough(): void
    {
        new SignedLinks(str_repeat('k', 16), $this->clock);

        $this->expectException(LinkRefused::class);
        $this->expectExceptionMessageIsOrContains('The key of the newsletter links holds at least 16 bytes.');
        new SignedLinks(str_repeat('k', 15), $this->clock);
    }

    public function testThePlaceholderOfTheRecipeIsRefused(): void
    {
        $this->expectExceptionObject(LinkRefused::because('The key of the newsletter links is a placeholder: give it random bytes of its own.'));

        new SignedLinks('change-me-to-a-random-secret-of-32-bytes-or-more', $this->clock);
    }

    private static function forged(string $payload): string
    {
        $encode = static fn (string $bytes): string => sodium_bin2base64($bytes, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);

        return $encode($payload).'.'.$encode(substr(hash_hmac('sha256', $payload, 'a-key-of-sixteen-bytes-at-least', true), 0, 16));
    }
}
