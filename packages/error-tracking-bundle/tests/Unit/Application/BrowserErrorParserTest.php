<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Unit\Application;

use Phil\ErrorTracking\Frame;
use Phil\ErrorTrackingBundle\Application\BrowserError;
use Phil\ErrorTrackingBundle\Application\BrowserErrorParser;
use Phil\ErrorTrackingBundle\Application\Exception\InvalidBrowserError;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(BrowserErrorParser::class)]
#[CoversClass(BrowserError::class)]
#[CoversClass(InvalidBrowserError::class)]
final class BrowserErrorParserTest extends TestCase
{
    public function testAChromiumStack(): void
    {
        $stack = "TypeError: Cannot read properties of undefined\n"
            ."    at Cart.total (https://example.com/assets/app-1a2b.js?v=3:10:15)\n"
            ."    at https://example.com/assets/app-1a2b.js:20:3\n"
            ."    at async load (https://cdn.example.net/lib.js#x:5:1)\n"
            .'    at <anonymous>';
        $error = new BrowserErrorParser()->parse((string) json_encode(['type' => 'TypeError', 'message' => 'Cannot read properties of undefined', 'url' => 'https://Example.com/cart', 'stack' => $stack]));

        self::assertSame('TypeError', $error->type);
        self::assertSame('Cannot read properties of undefined', $error->message);
        self::assertSame('https://example.com/cart', $error->url);
        self::assertEquals([
            new Frame('https://example.com/assets/app-1a2b.js', 10, 'Cart.total', true),
            new Frame('https://example.com/assets/app-1a2b.js', 20, null, true),
            new Frame('https://cdn.example.net/lib.js', 5, 'async load', false),
        ], $error->frames);
        self::assertNull($error->frames[1]->function);
    }

    public function testAGeckoStack(): void
    {
        $stack = "total@https://example.com:8443/app.js:10:15\n@https://example.com:8443/app.js:20:3\nload/<@https://example.com/app.js:1:1\n";
        $error = new BrowserErrorParser()->parse((string) json_encode(['message' => 'x is undefined', 'url' => 'https://example.com:8443/cart', 'stack' => $stack]));

        self::assertSame('Error', $error->type);
        self::assertEquals([
            new Frame('https://example.com:8443/app.js', 10, 'total', true),
            new Frame('https://example.com:8443/app.js', 20, null, true),
            new Frame('https://example.com/app.js', 1, 'load/<', false),
        ], $error->frames);
        self::assertNull($error->frames[1]->function);
    }

    public function testTheFramesAreBounded(): void
    {
        $stack = str_repeat("    at f (https://example.com/a.js:1:1)\n", 5);

        self::assertCount(3, new BrowserErrorParser(3)->parse((string) json_encode(['message' => 'm', 'url' => 'https://example.com/', 'stack' => $stack]))->frames);
        self::assertCount(5, new BrowserErrorParser()->parse((string) json_encode(['message' => 'm', 'url' => 'https://example.com/', 'stack' => $stack]))->frames);
        self::assertCount(1, new BrowserErrorParser(1)->parse((string) json_encode(['message' => 'm', 'url' => 'https://example.com/', 'stack' => $stack]))->frames);
        self::assertSame(50, BrowserErrorParser::DEFAULT_MAX_FRAMES);
    }

    public function testTheStackIsReadUpToItsBound(): void
    {
        $line = '    at f (https://example.com/a.js:1:1)';
        $cut = str_repeat(' ', BrowserErrorParser::MAX_STACK_LENGTH - \strlen($line) + 5)."\n".$line;
        $whole = str_repeat(' ', BrowserErrorParser::MAX_STACK_LENGTH - \strlen($line) - 1)."\n".$line;

        self::assertSame([], new BrowserErrorParser()->parse((string) json_encode(['message' => 'm', 'url' => 'https://example.com/', 'stack' => $cut]))->frames);
        self::assertCount(1, new BrowserErrorParser()->parse((string) json_encode(['message' => 'm', 'url' => 'https://example.com/', 'stack' => $whole]))->frames);
        self::assertSame(16_384, BrowserErrorParser::MAX_STACK_LENGTH);
    }

    public function testTheMessageAndTheFunctionLoseTheirControlCharacters(): void
    {
        $error = new BrowserErrorParser()->parse((string) json_encode(['message' => "a\n\tb\x7Fc".str_repeat('é', 3000), 'url' => 'http://example.com/', 'stack' => "    at f\x01g (https://example.com/a.js:1:1)"]));

        self::assertSame('a b c'.str_repeat('é', 2042), $error->message);
        self::assertSame('fg', $error->frames[0]->function);
    }

    #[TestWith(['Error'])]
    #[TestWith(['$Custom.Error_2'])]
    public function testAJavaScriptTypeIsKept(string $type): void
    {
        self::assertSame($type, new BrowserErrorParser()->parse((string) json_encode(['type' => $type, 'message' => 'm', 'url' => 'https://example.com/']))->type);
    }

    #[TestWith(['2Error'])]
    #[TestWith(['Type Error'])]
    #[TestWith(["Error\n"])]
    #[TestWith(['A1234567890123456789012345678901234567890123456789012345678901234'])]
    public function testAnotherTypeIsAnError(string $type): void
    {
        self::assertSame('Error', new BrowserErrorParser()->parse((string) json_encode(['type' => $type, 'message' => 'm', 'url' => 'https://example.com/']))->type);
        self::assertSame('Error', new BrowserErrorParser()->parse((string) json_encode(['type' => 3, 'message' => 'm', 'url' => 'https://example.com/']))->type);
        self::assertSame('A12345678901234567890123456789012345678901234567890123456789012', new BrowserErrorParser()->parse((string) json_encode(['type' => 'A12345678901234567890123456789012345678901234567890123456789012', 'message' => 'm', 'url' => 'https://example.com/']))->type);
    }

    #[TestWith(['not json', 'The report is not JSON.'])]
    #[TestWith(['{"message":"m","url":"https://example.com/","extra":[1]}', 'The report is not JSON.'])]
    #[TestWith(["{\"message\":\"a\xFFb\",\"url\":\"https://example.com/\"}", 'The report is not JSON.'])]
    #[TestWith(['[[[[["deep"]]]]]', 'The report is not JSON.'])]
    #[TestWith(['"text"', 'The report has no message or no page.'])]
    #[TestWith(['{"url":"https://example.com/"}', 'The report has no message or no page.'])]
    #[TestWith(['{"message":"m"}', 'The report has no message or no page.'])]
    #[TestWith(['{"message":"m","url":3}', 'The report has no message or no page.'])]
    #[TestWith(['{"message":"m","url":"javascript:alert(1)"}', 'The page is not an http address.'])]
    #[TestWith(['{"message":"m","url":"https://example.com/a b"}', 'The page is not an http address.'])]
    #[TestWith(['{"message":"m","url":"https://example.com/\u0000"}', 'The page is not an http address.'])]
    #[TestWith(['{"message":"m","url":"https://example.com/\n"}', 'The page is not an http address.'])]
    #[TestWith(['{"message":"m","url":"xhttps://example.com/"}', 'The page is not an http address.'])]
    public function testAnUnreadableReportIsRefused(string $body, string $message): void
    {
        $this->expectException(InvalidBrowserError::class);
        $this->expectExceptionMessageIsOrContains($message);

        new BrowserErrorParser()->parse($body);
    }

    public function testATooLongPageIsRefused(): void
    {
        $this->expectException(InvalidBrowserError::class);

        new BrowserErrorParser()->parse((string) json_encode(['message' => 'm', 'url' => 'https://example.com/'.str_repeat('a', 2029)]));
    }

    public function testAFlatReport(): void
    {
        self::assertSame('m', new BrowserErrorParser()->parse('{"message":"m","url":"https://example.com/","extra":1}')->message);
    }

    public function testAPageOfTheMaximumLength(): void
    {
        self::assertSame(2048, \strlen(new BrowserErrorParser()->parse((string) json_encode(['message' => 'm', 'url' => 'https://example.com/'.str_repeat('a', 2028)]))->url));
        self::assertSame('https://example.com/', new BrowserErrorParser()->parse('{"message":"m","url":"HTTPS://example.com/"}')->url);
    }

    public function testThePageIsNormalisedAndLosesItsQueryAndFragment(): void
    {
        self::assertSame('https://example.com/cart', new BrowserErrorParser()->parse('{"message":"m","url":"https://EXAMPLE.com:443/cart?coupon=SECRET#pay"}')->url);
        self::assertSame('http://localhost:8000/a/c', new BrowserErrorParser()->parse('{"message":"m","url":"http://localhost:8000/a/b/../c"}')->url);
        self::assertSame('https://xn--caf-dma.example/', new BrowserErrorParser()->parse('{"message":"m","url":"https://café.example"}')->url);
    }

    #[TestWith(['https://192.0.2.10/cart'])]
    #[TestWith(['https://user:secret@example.com/'])]
    #[TestWith(['https://example.com:0/'])]
    #[TestWith(['https:///cart'])]
    public function testAPageThatIsNotAnAddressOfAHostIsRefused(string $url): void
    {
        $this->expectException(InvalidBrowserError::class);
        $this->expectExceptionMessageIsOrContains('The page is not an http address.');

        new BrowserErrorParser()->parse((string) json_encode(['message' => 'm', 'url' => $url]));
    }

    public function testAFrameIsTheApplicationsByTheOriginOfItsNormalisedAddress(): void
    {
        $stack = "    at a (https://EXAMPLE.com:443/app.js:1:1)\n"
            ."    at b (https://192.0.2.10/app.js:2:1)\n"
            ."    at c (http://example.com/app.js:3:1)\n"
            ."    at d (https://example.com:8443/app.js:4:1)\n";
        $frames = new BrowserErrorParser()->parse((string) json_encode(['message' => 'm', 'url' => 'https://example.com/cart', 'stack' => $stack]))->frames;

        self::assertSame([true, false, false, false], array_map(static fn (Frame $frame): bool => $frame->inApp, $frames));
        self::assertSame('https://EXAMPLE.com:443/app.js', $frames[0]->file);
    }

    public function testTheOriginOfAnAddressIsReadFromOutsideTheParser(): void
    {
        self::assertSame('https://example.com', BrowserErrorParser::originOf(Url::fromString('HTTPS://EXAMPLE.com:443/a?b#c')));
        self::assertSame('http://example.com:8080', BrowserErrorParser::originOf(Url::fromString('http://example.com:8080/')));
    }
}
