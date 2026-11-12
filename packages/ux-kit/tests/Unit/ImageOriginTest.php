<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Unit;

use Phil\UxKit\Application\ImageOrigin;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImageOrigin::class)]
final class ImageOriginTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function local(): iterable
    {
        yield 'a path from the root' => ['/images/bridge.jpg'];
        yield 'a relative path' => ['images/bridge.jpg'];
        yield 'a path that climbs' => ['../images/bridge.jpg'];
        yield 'a query alone' => ['?v=2'];
        yield 'an inline image' => ['data:image/png;base64,AAAA'];
        yield 'an inline image in capitals' => ['DATA:image/svg+xml,%3Csvg%3E'];
        yield "the page's own origin" => ['https://www.example.org/images/bridge.jpg'];
        yield "the page's own origin in capitals" => ['HTTPS://WWW.EXAMPLE.ORG/images/bridge.jpg'];
        yield 'a path holding a scheme further on' => ['/images/a.jpg?from=data:text/html'];
        yield 'a path holding a colon' => ['/a:b.jpg'];
        yield 'spaces and line breaks around' => ["\n /images/\tbridge.jpg \n"];
    }

    #[DataProvider('local')]
    public function testAnAddressOfThePageIsLocal(string $url): void
    {
        self::assertSame(ImageOrigin::LOCAL, new ImageOrigin(['cdn.example.net'])->of($url, 'https://www.example.org'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function allowed(): iterable
    {
        yield 'a host of the list' => ['https://cdn.example.net/bridge.jpg'];
        yield 'a host under one of the list' => ['https://eu.cdn.example.net/bridge.jpg'];
        yield 'in capitals' => ['HTTPS://CDN.EXAMPLE.NET/bridge.jpg'];
        yield 'over http' => ['http://cdn.example.net/bridge.jpg'];
        yield 'without a scheme' => ['//cdn.example.net/bridge.jpg'];
        yield 'with a port' => ['https://cdn.example.net:8443/bridge.jpg'];
    }

    #[DataProvider('allowed')]
    public function testAHostOfTheListIsExternal(string $url): void
    {
        self::assertSame(ImageOrigin::EXTERNAL, new ImageOrigin(['cdn.example.net', 'photos.example.com'])->of($url, 'https://www.example.org'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refused(): iterable
    {
        yield 'another host' => ['https://tracker.example.com/pixel.gif'];
        yield 'a host that ends like one of the list' => ['https://evilcdn.example.net/bridge.jpg'];
        yield 'a host that starts like one of the list' => ['https://cdn.example.net.example.com/bridge.jpg'];
        yield 'credentials before the host' => ['https://cdn.example.net@tracker.example.com/pixel.gif'];
        yield 'another host without a scheme' => ['//tracker.example.com/pixel.gif'];
        yield 'a backslash for a slash' => ['/\\tracker.example.com/pixel.gif'];
        yield 'two backslashes' => ['\\\\tracker.example.com/pixel.gif'];
        yield 'an IP address' => ['https://192.0.2.1/pixel.gif'];
        yield 'localhost' => ['http://localhost/pixel.gif'];
        yield 'a script' => ['javascript:alert(1)'];
        yield 'a script cut by a tab' => ["java\tscript:alert(1)"];
        yield 'a document inline holding an image further on' => ['data:text/html,data:image/png'];
        yield 'a document inline' => ['data:text/html,<script>alert(1)</script>'];
        yield 'another scheme' => ['ftp://cdn.example.net/bridge.jpg'];
        yield 'a blob' => ['blob:https://www.example.org/1'];
        yield 'nothing' => [''];
        yield 'spaces only' => ['  '];
    }

    #[DataProvider('refused')]
    public function testAnyOtherAddressIsRefused(string $url): void
    {
        self::assertSame(ImageOrigin::REFUSED, new ImageOrigin(['cdn.example.net'])->of($url, 'https://www.example.org'));
    }

    public function testWithoutAListEveryOtherHostIsRefused(): void
    {
        self::assertSame(ImageOrigin::REFUSED, new ImageOrigin()->of('https://cdn.example.net/bridge.jpg'));
        self::assertSame(ImageOrigin::LOCAL, new ImageOrigin()->of('/bridge.jpg'));
    }

    public function testWithoutThePageTheOwnHostMustBeListed(): void
    {
        self::assertSame(ImageOrigin::REFUSED, new ImageOrigin()->of('https://www.example.org/bridge.jpg'));
        self::assertSame(ImageOrigin::EXTERNAL, new ImageOrigin(['www.example.org'])->of('https://www.example.org/bridge.jpg'));
    }

    public function testAnOriginIsMatchedWholeNeverAsTheStartOfAHost(): void
    {
        self::assertSame(ImageOrigin::REFUSED, new ImageOrigin()->of('https://www.example.org.example.com/a.jpg', 'https://www.example.org'));
        self::assertSame(ImageOrigin::REFUSED, new ImageOrigin()->of('https://www.example.org:8443/a.jpg', 'https://www.example.org'));
        self::assertSame(ImageOrigin::LOCAL, new ImageOrigin()->of('https://www.example.org:8443/a.jpg', 'https://www.example.org:8443'));
        self::assertSame(ImageOrigin::LOCAL, new ImageOrigin()->of('https://www.example.org', 'https://www.example.org'));
        self::assertSame(ImageOrigin::LOCAL, new ImageOrigin()->of('https://www.example.org/a.jpg', 'HTTPS://WWW.EXAMPLE.ORG'));
    }

    public function testLocalhostIsAHostOnlyWhenAllowed(): void
    {
        self::assertSame(ImageOrigin::EXTERNAL, new ImageOrigin(['localhost'], allowLocalhost: true)->of('http://localhost:8000/a.jpg'));
        self::assertSame(ImageOrigin::REFUSED, new ImageOrigin(['localhost'])->of('http://localhost:8000/a.jpg'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function sets(): iterable
    {
        yield 'local candidates' => ['/a-480.jpg 480w, /a-960.jpg 960w', ImageOrigin::LOCAL];
        yield 'densities without spaces' => ['/a.jpg 1x,/a@2x.jpg 2x', ImageOrigin::LOCAL];
        yield 'one candidate without descriptor' => ['/a.jpg', ImageOrigin::LOCAL];
        yield 'a candidate ended by its comma' => ['/a.jpg,/b.jpg 2x', ImageOrigin::LOCAL];
        yield 'an inline image holding commas' => ['data:image/svg+xml,%3Csvg%3E,x 1x, /a@2x.jpg 2x', ImageOrigin::LOCAL];
        yield 'one candidate of the list' => ['/a.jpg 1x, https://cdn.example.net/a@2x.jpg 2x', ImageOrigin::EXTERNAL];
        yield 'one candidate of another host' => ['/a.jpg 1x, https://tracker.example.com/a@2x.jpg 2x', ImageOrigin::REFUSED];
        yield 'a script among them' => ['/a.jpg 1x, javascript:alert(1) 2x', ImageOrigin::REFUSED];
        yield 'descriptors in parentheses' => ['/a.jpg (a, b) 1x, /b.jpg 2x', ImageOrigin::LOCAL];
        yield 'no candidate' => [' , ', ImageOrigin::REFUSED];
        yield 'nothing' => ['', ImageOrigin::REFUSED];
        yield 'a space before' => [' /a.jpg 1x', ImageOrigin::LOCAL];
        yield 'candidates split by tabs' => ["/a.jpg\t1x,\thttps://cdn.example.net/b.jpg\t2x", ImageOrigin::EXTERNAL];
        yield 'another host behind a comma' => ['/a.jpg 1x,https://tracker.example.com/b.jpg 2x', ImageOrigin::REFUSED];
        yield 'another host whose path looks like a descriptor' => ['/a.jpg 1x, https://tracker.example.com/2x 2x', ImageOrigin::REFUSED];
    }

    #[DataProvider('sets')]
    public function testASetIsAsItsWorstCandidate(string $set, string $origin): void
    {
        self::assertSame($origin, new ImageOrigin(['cdn.example.net'])->ofSet($set, 'https://www.example.org'));
    }
}
