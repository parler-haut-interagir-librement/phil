<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Tests\Unit\Application;

use Phil\EmbedBundle\Application\Embed;
use Phil\EmbedBundle\Application\EmbedCodes;
use Phil\EmbedBundle\Application\Embeds;
use Phil\EmbedBundle\Application\EmbedTokenCodec;
use Phil\EmbedBundle\Application\EmbedTokens;
use Phil\EmbedBundle\Domain\Exception\InvalidEmbed;
use Phil\EmbedBundle\Domain\ValueObject\Origin;
use Phil\EmbedBundle\Tests\Fixtures\Keys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

#[CoversClass(EmbedCodes::class)]
final class EmbedCodesTest extends TestCase
{
    public function testTheCodeIsALinkInAnElementAndTheScript(): void
    {
        $code = $this->codes()->snippet('calendar', Origin::fromString('https://partner.example'), 'calendar_embed', ['id' => 7], 'https://site.example/calendar/7', 'Our "calendar" <live>', 'dark', 'calendar/7');

        self::assertMatchesRegularExpression(
            '#^<phil-embed src="https://site\.example/embed/calendar/7\?embed_token=pe1\.hs256\.2026-11\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+" title="Our &quot;calendar&quot; &lt;live&gt;" theme="dark">\n    <a href="https://site\.example/calendar/7">Our &quot;calendar&quot; &lt;live&gt;</a>\n</phil-embed>\n<script type="module" src="https://site\.example/_embed/phil-embed\.js" crossorigin="anonymous"></script>$#',
            $code,
        );
    }

    public function testWithoutTheme(): void
    {
        $code = $this->codes()->snippet('calendar', Origin::fromString('https://partner.example'), 'calendar_embed', ['id' => 7], 'https://site.example/c', 'Calendar');

        self::assertStringContainsString('" title="Calendar">', $code);
        self::assertStringNotContainsString('theme=', $code);
    }

    public function testTheFallbackIsAnAddress(): void
    {
        foreach (['/calendar', 'javascript:alert(1)', 'http://localhost/c'] as $fallback) {
            try {
                $this->codes()->snippet('calendar', Origin::fromString('https://partner.example'), 'calendar_embed', ['id' => 1], $fallback, 'Calendar');
                self::fail('Refused: '.$fallback);
            } catch (InvalidEmbed $error) {
                self::assertStringStartsWith('The fallback of an embed is an address: ', $error->getMessage());
            }
        }

        self::assertStringContainsString('href="http://localhost/c"', $this->codes(true)->snippet('calendar', Origin::fromString('https://partner.example'), 'calendar_embed', ['id' => 1], 'http://localhost/c', 'Calendar'));
    }

    public function testLocalhostIsNotAnAddressByDefault(): void
    {
        $clock = new MockClock('@1000');
        $codes = new EmbedCodes(new EmbedTokens(new EmbedTokenCodec(Keys::both(), $clock), new Embeds([Embed::of('calendar')]), $clock), new UrlGenerator(new RouteCollection(), new RequestContext()));

        $this->expectException(InvalidEmbed::class);

        $codes->snippet('calendar', Origin::fromString('https://partner.example'), 'calendar_embed', [], 'http://localhost/c', 'Calendar');
    }

    public function testTheValuesAreEscapedAsHtml5(): void
    {
        $code = $this->codes()->snippet('calendar', Origin::fromString('https://partner.example'), 'calendar_embed', ['id' => 7], 'https://site.example/c', "It's");

        self::assertStringContainsString('title="It&apos;s"', $code);
    }

    public function testTheCodeHasATitle(): void
    {
        $this->expectExceptionObject(InvalidEmbed::because("The code of an embed names it: its title is the frame's and the link's."));

        $this->codes()->snippet('calendar', Origin::fromString('https://partner.example'), 'calendar_embed', ['id' => 1], 'https://site.example/c', ' ');
    }

    private function codes(bool $allowLocalhost = false): EmbedCodes
    {
        $routes = new RouteCollection();
        $routes->add('calendar_embed', new Route('/embed/calendar/{id}'));
        $routes->add(EmbedCodes::SCRIPT_ROUTE, new Route('/_embed/{file}', requirements: ['file' => 'phil-embed\.js|lib/message\.js']));

        $clock = new MockClock('@1000');
        $tokens = new EmbedTokens(new EmbedTokenCodec(Keys::both(), $clock), new Embeds([Embed::of('calendar', themes: ['dark'])]), $clock);

        return new EmbedCodes($tokens, new UrlGenerator($routes, new RequestContext(host: 'site.example', scheme: 'https')), $allowLocalhost);
    }
}
