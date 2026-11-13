<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Tests\Unit\Application;

use Phil\EmbedBundle\Application\Embed;
use Phil\EmbedBundle\Application\EmbedContext;
use Phil\EmbedBundle\Application\Embeds;
use Phil\EmbedBundle\Application\EmbedTokenCodec;
use Phil\EmbedBundle\Application\EmbedTokens;
use Phil\EmbedBundle\Domain\Exception\InvalidEmbed;
use Phil\EmbedBundle\Domain\Exception\InvalidEmbedToken;
use Phil\EmbedBundle\Domain\ValueObject\Origin;
use Phil\EmbedBundle\Tests\Fixtures\Keys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(EmbedTokens::class)]
#[CoversClass(EmbedContext::class)]
final class EmbedTokensTest extends TestCase
{
    public function testATokenIsIssuedForAnAllowedOriginAndReadIntoItsContext(): void
    {
        $tokens = $this->tokens();
        $token = $tokens->issue('calendar', Origin::fromString('https://partner.example'), 'dark', 'event/1');

        self::assertEquals(new EmbedContext('calendar', 'https://partner.example', 'dark', 'event/1', ['light', 'dark']), $tokens->context('calendar', $token));
        self::assertSame(1000 + 3600, new EmbedTokenCodec(Keys::both(), new MockClock('@1000'))->decode($token)->expiresAt);
    }

    public function testTheThemeOfThePageThenOfTheTokenThenTheDefault(): void
    {
        $tokens = $this->tokens();
        $origin = Origin::fromString('https://partner.example');

        self::assertSame('light', $tokens->context('calendar', $tokens->issue('calendar', $origin, 'dark'), 'light')->theme);
        self::assertSame('dark', $tokens->context('calendar', $tokens->issue('calendar', $origin, 'dark'), 'blue')->theme, 'a theme the embed lacks is ignored');
        self::assertSame('light', $tokens->context('calendar', $tokens->issue('calendar', $origin))->theme, 'the default theme');
        self::assertNull($tokens->context('form', $tokens->issue('form', Origin::fromString('https://any.example')))->theme);
    }

    public function testAnEmbedWithoutLifetimeIssuesTokensThatDoNotExpire(): void
    {
        $token = $this->tokens()->issue('form', Origin::fromString('https://any.example'));

        self::assertNull(new EmbedTokenCodec(Keys::both(), new MockClock('@1000'))->decode($token)->expiresAt);
    }

    public function testWhatIsNotIssued(): void
    {
        $tokens = $this->tokens();

        foreach ([
            ['map', 'https://partner.example', null, 'No embed is named "map".'],
            ['calendar', 'https://other.example', null, 'The embed "calendar" does not allow https://other.example.'],
            ['calendar', 'https://partner.example', 'blue', 'The embed "calendar" has no theme "blue".'],
        ] as [$embed, $origin, $theme, $message]) {
            try {
                $tokens->issue($embed, Origin::fromString($origin), $theme);
                self::fail('Refused.');
            } catch (InvalidEmbed $error) {
                self::assertSame($message, $error->getMessage());
            }
        }
    }

    public function testATokenOfAnotherEmbedIsRefused(): void
    {
        $tokens = $this->tokens();

        $this->expectExceptionObject(InvalidEmbedToken::because('The token is for the embed "form", not "calendar".'));
        $tokens->context('calendar', $tokens->issue('form', Origin::fromString('https://partner.example')));
    }

    public function testATokenOfAnEmbedRemovedIsRefused(): void
    {
        $token = $this->tokens()->issue('form', Origin::fromString('https://partner.example'));
        $tokens = new EmbedTokens(new EmbedTokenCodec(Keys::both(), new MockClock('@1000')), new Embeds(), new MockClock('@1000'));

        $this->expectExceptionObject(InvalidEmbedToken::because('No embed is named "form".'));
        $tokens->context('form', $token);
    }

    public function testAnOriginNoLongerAllowedIsRefused(): void
    {
        $token = $this->tokens()->issue('form', Origin::fromString('https://partner.example'));
        $narrower = new EmbedTokens(new EmbedTokenCodec(Keys::both(), new MockClock('@1000')), new Embeds([Embed::of('form', [Origin::fromString('https://other.example')])]), new MockClock('@1000'));

        $this->expectExceptionObject(InvalidEmbedToken::because('The embed "form" no longer allows https://partner.example.'));
        $narrower->context('form', $token);
    }

    private function tokens(): EmbedTokens
    {
        $clock = new MockClock('@1000');

        return new EmbedTokens(new EmbedTokenCodec(Keys::both(), $clock), new Embeds([
            Embed::of('calendar', [Origin::fromString('https://partner.example')], ['light', 'dark'], 'light', 3600),
            Embed::of('form'),
        ]), $clock);
    }
}
