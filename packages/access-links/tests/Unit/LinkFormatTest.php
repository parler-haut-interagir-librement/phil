<?php

declare(strict_types=1);

namespace Phil\AccessLinks\Tests\Unit;

use Phil\AccessLinks\Exception\InvalidConfiguration;
use Phil\AccessLinks\Exception\InvalidToken;
use Phil\AccessLinks\LinkFormat;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LinkFormat::class)]
final class LinkFormatTest extends TestCase
{
    private const string TOKEN = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';

    public function testTheLinkIsThePageWithTheToken(): void
    {
        self::assertSame('https://events.example/rsvp?token='.self::TOKEN, new LinkFormat(Url::fromString('https://events.example/rsvp'))->for(self::TOKEN)->toString());
    }

    public function testTheTokenFollowsTheQueryOfThePage(): void
    {
        $format = new LinkFormat(Url::fromString('https://events.example:8443/fr/rsvp?lang=fr'), 't');

        self::assertSame('https://events.example:8443/fr/rsvp?lang=fr&t='.self::TOKEN, $format->for(self::TOKEN)->toString());
    }

    public function testAPageOnLocalhostKeepsItsHost(): void
    {
        self::assertSame('http://app.localhost/rsvp?token='.self::TOKEN, new LinkFormat(Url::fromString('http://app.localhost/rsvp', allowLocalhost: true))->for(self::TOKEN)->toString());
    }

    public function testOnlyATokenIsCarried(): void
    {
        $this->expectException(InvalidToken::class);

        new LinkFormat(Url::fromString('https://events.example/rsvp'))->for('alice@example.com');
    }

    public function testTheParameterIsANameOfTheQuery(): void
    {
        self::assertSame(str_repeat('a', 32), new LinkFormat(Url::fromString('https://events.example/'), str_repeat('a', 32))->parameter);

        foreach (['', '1a', 'A', 'a-b', str_repeat('a', 33), "a\n"] as $parameter) {
            try {
                new LinkFormat(Url::fromString('https://events.example/'), $parameter);
                self::fail(\sprintf('The parameter "%s" was accepted.', $parameter));
            } catch (InvalidConfiguration $exception) {
                self::assertStringStartsWith('The parameter of the token is 1 to 32 lowercase letters', $exception->getMessage());
            }
        }
    }

    public function testThePageHasNoFragment(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains('The address of the invitation page has no fragment.');

        new LinkFormat(Url::fromString('https://events.example/rsvp#top'));
    }

    public function testAnAddressTooLongIsRefused(): void
    {
        $format = new LinkFormat(Url::fromString('https://events.example/'.str_repeat('a', 1990)));

        $this->expectException(InvalidConfiguration::class);

        $format->for(self::TOKEN);
    }
}
