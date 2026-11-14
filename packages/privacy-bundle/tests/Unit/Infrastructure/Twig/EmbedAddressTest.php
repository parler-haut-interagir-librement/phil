<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Twig;

use Phil\PrivacyBundle\Domain\Consent\ConsentCategory;
use Phil\PrivacyBundle\Domain\Consent\ConsentPolicy;
use Phil\PrivacyBundle\Domain\Consent\ConsentToken;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Infrastructure\Http\ConsentContext;
use Phil\PrivacyBundle\Infrastructure\Http\ConsentCookie;
use Phil\PrivacyBundle\Infrastructure\Http\PrivacyPages;
use Phil\PrivacyBundle\Infrastructure\Twig\EmbedExtension;
use Phil\Url\CookielessVideo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * The address a template gives privacy_embed(): checked, and turned into the player without cookies of phil/url for a
 * YouTube or Vimeo video.
 */
#[CoversClass(EmbedExtension::class)]
final class EmbedAddressTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function youtube(): iterable
    {
        yield 'watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'watch on the mobile site' => ['https://m.youtube.com/watch?v=dQw4w9WgXcQ&feature=share', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'short link with seconds' => ['https://youtu.be/dQw4w9WgXcQ?t=42', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=42'];
        yield 'short link with a duration' => ['https://youtu.be/dQw4w9WgXcQ?t=1h2m3s', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=3723'];
        yield 'seconds with an s' => ['https://youtu.be/dQw4w9WgXcQ?t=90s', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=90'];
        yield 'minutes only' => ['https://youtu.be/dQw4w9WgXcQ?t=2m', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=120'];
        yield 'embed with start' => ['https://www.youtube.com/embed/dQw4w9WgXcQ?start=10', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=10'];
        yield 'zero start' => ['https://www.youtube.com/embed/dQw4w9WgXcQ?start=0', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'a start of words' => ['https://www.youtube.com/embed/dQw4w9WgXcQ?start=soon', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'shorts' => ['https://youtube.com/shorts/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'live' => ['https://www.youtube.com/live/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'already without cookies' => ['https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'host in capitals' => ['HTTPS://WWW.YOUTUBE.COM/watch?v=dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'start before t' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=20&start=10', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=10'];
        yield 'hours only' => ['https://youtu.be/dQw4w9WgXcQ?t=1h', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=3600'];
        yield 'an empty start' => ['https://youtu.be/dQw4w9WgXcQ?t=', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'a start after other characters' => ['https://youtu.be/dQw4w9WgXcQ?t=x90', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'a start before other characters' => ['https://youtu.be/dQw4w9WgXcQ?t=90x', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'a start before a line break' => ['https://youtu.be/dQw4w9WgXcQ?t=90%0A', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'a start as an array' => ['https://youtu.be/dQw4w9WgXcQ?t[]=90', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
    }

    #[DataProvider('youtube')]
    public function testEveryFormOfAYouTubeVideoBecomesItsVersionWithoutCookies(string $url, string $expected): void
    {
        self::assertSame([$expected, $url, 'YouTube'], $this->source($url));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function vimeo(): iterable
    {
        yield 'page' => ['https://vimeo.com/76979871', 'https://player.vimeo.com/video/76979871?dnt=1'];
        yield 'unlisted page' => ['https://vimeo.com/76979871/abc123', 'https://player.vimeo.com/video/76979871?h=abc123&dnt=1'];
        yield 'player' => ['https://player.vimeo.com/video/76979871', 'https://player.vimeo.com/video/76979871?dnt=1'];
        yield 'player with a hash' => ['https://player.vimeo.com/video/76979871?h=ff00', 'https://player.vimeo.com/video/76979871?h=ff00&dnt=1'];
        yield 'player with a bad hash' => ['https://player.vimeo.com/video/76979871?h=<x>', 'https://player.vimeo.com/video/76979871?dnt=1'];
        yield 'player with a hash after other characters' => ['https://player.vimeo.com/video/76979871?h=xyz123', 'https://player.vimeo.com/video/76979871?dnt=1'];
        yield 'player with a hash before other characters' => ['https://player.vimeo.com/video/76979871?h=ff00xyz', 'https://player.vimeo.com/video/76979871?dnt=1'];
        yield 'player with a hash before a line break' => ['https://player.vimeo.com/video/76979871?h=ff00%0A', 'https://player.vimeo.com/video/76979871?dnt=1'];
        yield 'trailing slash' => ['https://www.vimeo.com/76979871/', 'https://player.vimeo.com/video/76979871?dnt=1'];
    }

    #[DataProvider('vimeo')]
    public function testAVimeoVideoAsksTheDoNotTrackPlayer(string $url, string $expected): void
    {
        self::assertSame([$expected, $url, 'Vimeo'], $this->source($url));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function keptAsGiven(): iterable
    {
        yield 'a map' => ['https://www.openstreetmap.org/export/embed.html?bbox=1,2,3,4', 'www.openstreetmap.org'];
        yield 'a YouTube channel' => ['https://www.youtube.com/@channel', 'www.youtube.com'];
        yield 'a YouTube id too short' => ['https://youtu.be/abc', 'youtu.be'];
        yield 'a YouTube watch without an id' => ['https://www.youtube.com/watch', 'www.youtube.com'];
        yield 'a YouTube watch with an array' => ['https://www.youtube.com/watch?v[]=dQw4w9WgXcQ', 'www.youtube.com'];
        yield 'a Vimeo channel' => ['https://vimeo.com/channels/staff', 'vimeo.com'];
        yield 'a Vimeo video under a channel' => ['https://vimeo.com/channels/staffpicks/76979871', 'vimeo.com'];
        yield 'a Vimeo video page with more after it' => ['https://vimeo.com/76979871/settings', 'vimeo.com'];
        yield 'a lookalike host' => ['https://youtube.com.example.org/watch?v=dQw4w9WgXcQ', 'youtube.com.example.org'];
        yield 'a host in capitals' => ['https://Maps.Example.ORG/embed', 'maps.example.org'];
        yield 'an international host' => ['https://bücher.example/embed', 'xn--bcher-kva.example'];
        yield 'a host with its root dot' => ['https://maps.example.org./embed', 'maps.example.org'];
    }

    #[DataProvider('keptAsGiven')]
    public function testAnyOtherHttpsAddressIsKeptAsGivenWithItsHostAsTheProvider(string $url, string $provider): void
    {
        self::assertSame([$url, $url, $provider], $this->source($url));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refused(): iterable
    {
        yield 'empty' => [''];
        yield 'plain HTTP' => ['http://example.org/frame'];
        yield 'a script' => ['javascript:alert(1)'];
        yield 'relative' => ['/frame'];
        yield 'no host' => ['https:///frame'];
        yield 'a host of dots' => ['https://.'];
        yield 'a host of dots and a path' => ['https://.../frame'];
        yield 'credentials' => ['https://user:pass@example.org/'];
        yield 'a user' => ['https://user@example.org/'];
        yield 'a space' => ['https://example.org/a b'];
        yield 'a line break' => ["https://example.org/\n"];
        yield 'a host that is an IP address' => ['https://192.0.2.1/watch?v=dQw4w9WgXcQ'];
        yield 'a host of IPv6' => ['https://[2001:db8::1]/frame'];
        yield 'localhost' => ['https://localhost/frame'];
        yield 'a single name' => ['https://intranet/frame'];
        yield 'port zero' => ['https://example.org:0/frame'];
        yield 'a host of empty labels' => ['https://a..b/frame'];
        yield 'too long' => ['https://example.org/'.str_repeat('a', 2029)];
    }

    #[DataProvider('refused')]
    public function testWhatIsNotAnHttpsAddressIsRefused(string $url): void
    {
        $this->expectException(InvalidPrivacyValue::class);

        $this->source($url);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedBeforeBeingParsed(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => ['https://example.org/'.str_repeat('a', 2029)];
    }

    #[DataProvider('refusedBeforeBeingParsed')]
    public function testAnEmptyOrTooLongAddressIsRefusedByItsLengthNotByItsShape(string $url): void
    {
        try {
            $this->source($url);
        } catch (InvalidPrivacyValue $invalidPrivacyValue) {
            self::assertStringContainsString('at most 2048 characters', $invalidPrivacyValue->getMessage());

            return;
        }

        self::fail('The address should have been refused.');
    }

    public function testTheLongestAddressIsKept(): void
    {
        $url = 'https://example.org/'.str_repeat('a', 2028);

        self::assertSame(2048, \strlen($url));
        self::assertSame($url, $this->source($url)[0]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function videos(): iterable
    {
        yield 'YouTube on another of its hosts' => ['https://gaming.youtube.com/watch?v=dQw4w9WgXcQ&t=1m'];
        yield 'YouTube with a port' => ['https://www.youtube.com:443/embed/dQw4w9WgXcQ'];
        yield 'Vimeo' => ['https://vimeo.com/76979871/abc123'];
    }

    #[DataProvider('videos')]
    public function testTheVersionWithoutCookiesIsTheOneOfPhilUrl(string $url): void
    {
        $video = CookielessVideo::fromUrl($url);

        self::assertInstanceOf(CookielessVideo::class, $video);
        self::assertSame([$video->url->toString(), $url, $video->type->label()], $this->source($url));
    }

    /**
     * @return list<string> the frame's address, the address of the link, and the provider, as the template gets them
     */
    private function source(string $url): array
    {
        $tokens = self::createStub(CsrfTokenManagerInterface::class);
        $tokens->method('getToken')->willReturn(new CsrfToken('phil_privacy', 'tok'));
        $twig = new Environment(new ArrayLoader(['@PhilPrivacy/embed/frame.html.twig' => '{{ source.url }}|{{ source.original }}|{{ provider }}']), ['autoescape' => false]);
        $policy = new ConsentPolicy('1', [new ConsentCategory('videos')]);
        $stack = new RequestStack();
        $context = new ConsentContext($stack, new ConsentCookie(new ConsentToken('s'), new MockClock(), 'c', 'P1D'), $policy);

        return explode('|', new EmbedExtension($context, $policy, new PrivacyPages($twig, $tokens, self::createStub(UrlGeneratorInterface::class), 'l'), $stack)->embed($twig, $url, 'videos', ['title' => 't']));
    }
}
