<?php

declare(strict_types=1);

namespace Phil\Url\Tests\Unit;

use Phil\Url\CookielessVideo;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\KnownUrlType;
use Phil\Url\TypedUrl;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CookielessVideo::class)]
#[CoversClass(KnownUrlType::class)]
final class CookielessVideoTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function youtube(): iterable
    {
        yield 'watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'watch on the mobile site' => ['https://m.youtube.com/watch?v=dQw4w9WgXcQ&feature=share', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'watch in plain HTTP' => ['http://youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'short link with seconds' => ['https://youtu.be/dQw4w9WgXcQ?t=42', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=42'];
        yield 'short link with a duration' => ['https://youtu.be/dQw4w9WgXcQ?t=1h2m3s', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=3723'];
        yield 'hours only' => ['https://youtu.be/dQw4w9WgXcQ?t=1h', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=3600'];
        yield 'seconds with an s' => ['https://youtu.be/dQw4w9WgXcQ?t=90s', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=90'];
        yield 'minutes only' => ['https://youtu.be/dQw4w9WgXcQ?t=2m', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=120'];
        yield 'start before t' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=5&start=7', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=7'];
        yield 'embed with start' => ['https://www.youtube.com/embed/dQw4w9WgXcQ?start=10', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=10'];
        yield 'zero start' => ['https://www.youtube.com/embed/dQw4w9WgXcQ?start=0', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'an empty start' => ['https://www.youtube.com/embed/dQw4w9WgXcQ?start=', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'a start of words' => ['https://www.youtube.com/embed/dQw4w9WgXcQ?start=soon', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'a start with words around' => ['https://www.youtube.com/embed/dQw4w9WgXcQ?start=x90y', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'a start with words before' => ['https://www.youtube.com/embed/dQw4w9WgXcQ?start=x90', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'a start with words after' => ['https://www.youtube.com/embed/dQw4w9WgXcQ?start=90x', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'a start ending with a new line' => ['https://www.youtube.com/embed/dQw4w9WgXcQ?start=90%0A', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'a start as an array' => ['https://www.youtube.com/embed/dQw4w9WgXcQ?start[]=9', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'shorts' => ['https://youtube.com/shorts/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'live' => ['https://www.youtube.com/live/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'old v path' => ['https://www.youtube.com/v/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'music' => ['https://music.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'already without cookies' => ['https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
        yield 'host in capitals' => ['HTTPS://WWW.YOUTUBE.COM/watch?v=dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
    }

    #[DataProvider('youtube')]
    public function testEveryFormOfAYouTubeVideoBecomesItsPlayerWithoutCookies(string $text, string $expected): void
    {
        $video = CookielessVideo::fromUrl($text);

        self::assertInstanceOf(CookielessVideo::class, $video);
        self::assertSame(KnownUrlType::YouTube, $video->type);
        self::assertSame($expected, $video->url->toString());
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
        yield 'player with a hash then words' => ['https://player.vimeo.com/video/76979871?h=ff00zz', 'https://player.vimeo.com/video/76979871?dnt=1'];
        yield 'player with a hash ending with a new line' => ['https://player.vimeo.com/video/76979871?h=ff00%0A', 'https://player.vimeo.com/video/76979871?dnt=1'];
        yield 'player with a hash in capitals' => ['https://player.vimeo.com/video/76979871?h=FF00', 'https://player.vimeo.com/video/76979871?dnt=1'];
        yield 'player with a hash as an array' => ['https://player.vimeo.com/video/76979871?h[]=ff00', 'https://player.vimeo.com/video/76979871?dnt=1'];
        yield 'trailing slash' => ['https://www.vimeo.com/76979871/', 'https://player.vimeo.com/video/76979871?dnt=1'];
    }

    #[DataProvider('vimeo')]
    public function testAVimeoVideoAsksForThePlayerThatDoesNotTrack(string $text, string $expected): void
    {
        $video = CookielessVideo::fromUrl(Url::fromString($text));

        self::assertInstanceOf(CookielessVideo::class, $video);
        self::assertSame(KnownUrlType::Vimeo, $video->type);
        self::assertSame($expected, $video->url->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function noVideo(): iterable
    {
        yield 'a map' => ['https://www.openstreetmap.org/export/embed.html?bbox=1,2,3,4'];
        yield 'a YouTube channel' => ['https://www.youtube.com/@channel'];
        yield 'a YouTube home' => ['https://www.youtube.com/'];
        yield 'a short link home' => ['https://youtu.be/'];
        yield 'a YouTube id too short' => ['https://youtu.be/abc'];
        yield 'a YouTube id too long' => ['https://youtu.be/dQw4w9WgXcQx'];
        yield 'a YouTube id with a dot' => ['https://youtu.be/dQw4w9WgX.Q'];
        yield 'a YouTube watch without an id' => ['https://www.youtube.com/watch'];
        yield 'a YouTube watch under a path' => ['https://www.youtube.com/a/watch?v=dQw4w9WgXcQ'];
        yield 'a YouTube watch with an array' => ['https://www.youtube.com/watch?v[]=dQw4w9WgXcQ'];
        yield 'a YouTube embed without an id' => ['https://www.youtube.com/embed/'];
        yield 'a short link under another domain' => ['https://www.youtube.com/dQw4w9WgXcQ'];
        yield 'a Vimeo channel' => ['https://vimeo.com/channels/staff'];
        yield 'a Vimeo id under a path' => ['https://vimeo.com/x/76979871'];
        yield 'a Vimeo id with a letter' => ['https://vimeo.com/76979871a'];
        yield 'a Vimeo id too long' => ['https://vimeo.com/123456789012345678901'];
        yield 'a Vimeo hash too long' => ['https://vimeo.com/76979871/'.str_repeat('a', 33)];
        yield 'a lookalike host' => ['https://youtube.com.example.org/watch?v=dQw4w9WgXcQ'];
        yield 'a Vimeo path on YouTube' => ['https://www.youtube.com/video/76979871'];
    }

    #[DataProvider('noVideo')]
    public function testAnyOtherAddressHasNoPlayerWithoutCookies(string $text): void
    {
        self::assertNull(CookielessVideo::fromUrl($text));
    }

    public function testATextThatIsNoAddressIsRefused(): void
    {
        $this->expectException(InvalidUrl::class);

        CookielessVideo::fromUrl('javascript:alert(1)');
    }

    public function testThePlayerWithoutCookiesIsAYouTubeAddress(): void
    {
        $video = CookielessVideo::fromUrl('https://youtu.be/dQw4w9WgXcQ');

        self::assertInstanceOf(CookielessVideo::class, $video);
        self::assertTrue(KnownUrlType::YouTube->accepts($video->url));
        self::assertSame(KnownUrlType::YouTube, TypedUrl::of(KnownUrlType::YouTube, $video->url)->type);
    }
}
