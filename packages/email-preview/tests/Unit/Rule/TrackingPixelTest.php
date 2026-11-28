<?php

declare(strict_types=1);

namespace Phil\EmailPreview\Tests\Unit\Rule;

use Phil\EmailPreview\Rule\TrackingPixel;
use Phil\EmailPreview\Sending;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(TrackingPixel::class)]
final class TrackingPixelTest extends RuleTestCase
{
    private const string PIXEL = '[error] tracking-pixel: A remote image of one pixel tracks the opening of the email.';

    private const string PROPER = '[error] tracking-pixel: The address of a remote image is proper to the recipient.';

    public function testTheRuleIsNamed(): void
    {
        self::assertSame('tracking-pixel', new TrackingPixel()->name());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pixels(): iterable
    {
        yield 'attributes' => ['<img src="https://t.test/o.gif" width="1" height="1">'];
        yield 'zero' => ['<img src="http://t.test/o.gif" width="0" height="0" alt="">'];
        yield 'pixels in attributes' => ['<img src="//t.test/o.gif" width="1px" height="1PX">'];
        yield 'style' => ['<img src="HTTPS://t.test/o.gif" style="width: 1px; height:1px !important">'];
        yield 'style over attributes' => ['<img src="https://t.test/o.gif" width="200" height="100" style="width:1px;height:1px">'];
        yield 'mixed' => ['<img src=" https://t.test/o.gif " width="1" style="height: 0">'];
    }

    #[DataProvider('pixels')]
    public function testARemoteImageOfOnePixelIsAnError(string $html): void
    {
        self::assertCount(1, $findings = self::findings(new TrackingPixel(), $html));
        self::assertStringStartsWith(self::PIXEL.' (', $findings[0]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function images(): iterable
    {
        yield 'a real image' => ['<img src="https://a.test/logo.png" width="120" height="40">'];
        yield 'one side only' => ['<img src="https://a.test/line.png" width="1" height="20">'];
        yield 'the other side only' => ['<img src="https://a.test/line.png" width="600" height="1">'];
        yield 'no size' => ['<img src="https://a.test/logo.png">'];
        yield 'width only' => ['<img src="https://a.test/logo.png" width="1">'];
        yield 'a percentage' => ['<img src="https://a.test/logo.png" width="1%" height="1%">'];
        yield 'embedded' => ['<img src="cid:logo" width="1" height="1">'];
        yield 'written in' => ['<img src="data:image/gif;base64,R0lGOD" width="1" height="1">'];
        yield 'relative' => ['<img src="/o.gif" width="1" height="1">'];
        yield 'two pixels' => ['<img src="https://a.test/o.gif" width="2" height="1">'];
    }

    #[DataProvider('images')]
    public function testOtherImagesPass(string $html): void
    {
        self::assertSame([], self::findings(new TrackingPixel(), $html));
    }

    public function testAnImageAddressThatCarriesTheRecipientIsAnError(): void
    {
        $sending = new Sending(recipientTokens: ['Ana@A.test', 'user 42']);
        $html = '<img src="https://t.test/i.png?u=ana@a.test"><img src="https://t.test/user%2042/i.png"><img src="https://a.test/logo.png"><img src="cid:ana@a.test">';

        self::assertSame([
            self::PROPER.' (https://t.test/i.png?u=ana@a.test)',
            self::PROPER.' (https://t.test/user%2042/i.png)',
        ], self::findings(new TrackingPixel(), $html, sending: $sending));
    }

    public function testAnEncodedAddressIsFoundAndAnImageIsReportedOnce(): void
    {
        $sending = new Sending(recipientTokens: ['ana@a.test', 'ana']);

        self::assertSame([self::PROPER.' (https://t.test/i.png?u=ana%40a.test)'], self::findings(new TrackingPixel(), '<img src="https://t.test/i.png?u=ana%40a.test">', sending: $sending));
    }

    public function testATinyImageIsReportedAsAPixelOnly(): void
    {
        $sending = new Sending(recipientTokens: ['ana']);

        self::assertSame([self::PIXEL.' (https://t.test/ana.gif)'], self::findings(new TrackingPixel(), '<img src="https://t.test/ana.gif" width="1" height="1">', sending: $sending));
    }

    public function testEachImageIsCheckedAfterAnImageThatIsNotRemoteOrThatIsAPixel(): void
    {
        $html = '<img src="cid:logo"><img src="https://t.test/a.gif" width="1" height="1"><img src="https://t.test/b.gif" style="width:1px;height:1px">';

        self::assertSame([
            self::PIXEL.' (https://t.test/a.gif)',
            self::PIXEL.' (https://t.test/b.gif)',
        ], self::findings(new TrackingPixel(), $html));
    }
}
