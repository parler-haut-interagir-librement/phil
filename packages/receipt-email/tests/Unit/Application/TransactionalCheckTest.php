<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Tests\Unit\Application;

use Phil\ReceiptEmail\Application\RenderedReceipt;
use Phil\ReceiptEmail\Application\TransactionalCheck;
use Phil\ReceiptEmail\Domain\Exception\NotTransactional;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TransactionalCheck::class)]
#[CoversClass(NotTransactional::class)]
final class TransactionalCheckTest extends TestCase
{
    public function testAReceiptWithEmbeddedImagesAndPlainLinksPasses(): void
    {
        $html = '<html><head><style>.logo{background:url(cid:logo)} .x{background:url( "CID:mark" )}</style></head><body>'
            .'<img src="cid:logo" alt=""><img src=" CID:mark ">'
            // A cell stands in a table: the parser drops a cell standing alone.
            .'<table><tr><td background="cid:paper" style="background-image:url(\'cid:paper\')">Paid</td></tr></table>'
            .'<a href="https://shop.example/orders/A-1?view=receipt&amp;lang=fr#utm_source=x">Order</a>'
            // A "?" after the "#" starts no query, and an address without "?" has none.
            .'<a href="https://shop.example/orders/A-1#?utm_source=x">Top</a><a href="/utm_source">Path</a>'
            .'<a href="https://shop.example/help?subject=utm&q=gclid">Help</a><a href="mailto:shop@example.com">Write</a>'
            .'<meta name="color-scheme" content="light dark"><meta http-equiv="X-UA-Compatible" content="IE=edge"></body></html>';

        $receipt = new RenderedReceipt('Receipt', '', $html, 'Paid');

        self::assertSame($receipt, new TransactionalCheck()->check($receipt));
    }

    public function testAReceiptHasAPlainTextPart(): void
    {
        $this->expectException(NotTransactional::class);
        $this->expectExceptionMessageIs('A receipt has a plain-text part beside its HTML.');

        new TransactionalCheck()->check(new RenderedReceipt('Receipt', '', '<p>Paid</p>', " \n\t"));
    }

    public function testTheHtmlIsBounded(): void
    {
        $check = new TransactionalCheck();
        $largest = new RenderedReceipt('Receipt', '', str_pad('<p>', TransactionalCheck::MAX_HTML_BYTES, ' '), 'Paid');
        self::assertSame($largest, $check->check($largest));

        $this->expectExceptionMessageIs('A receipt has at most 2048 KiB of HTML.');

        $check->check(new RenderedReceipt('Receipt', '', str_pad('<p>', TransactionalCheck::MAX_HTML_BYTES + 1, ' '), 'Paid'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refused(): iterable
    {
        $pixel = 'An image of a receipt is embedded and shown as "cid:", never loaded from an address: no tracking pixel.';
        $remote = 'A receipt loads nothing: no script, frame, object, media, linked style sheet, srcset or refresh.';
        $style = 'A style\'s image of a receipt is embedded and shown as "cid:", never loaded from an address: no tracking pixel.';
        $link = 'A link of a receipt carries no tracking parameter.';

        yield 'a tracking pixel' => ['<img src="https://track.example/open.gif?u=42" width="1" height="1">', $pixel];
        yield 'an image without a source' => ['<img alt="">', $pixel];
        yield 'an image as data' => ['<img src="data:image/gif;base64,R0lGOD">', $pixel];
        yield 'an image whose address only contains cid:' => ['<img src="https://x.example/cid:logo">', $pixel];
        yield 'a background' => ['<table background="https://x.example/bg.png"></table>', 'A background of a receipt is embedded and shown as "cid:", never loaded from an address: no tracking pixel.'];
        yield 'a style attribute' => ['<div style="background:url(https://x.example/p.gif)"></div>', $style];
        yield 'a second image of a style' => ['<div style="background:url(cid:a), url(\'//x.example/p.gif\')"></div>', $style];
        yield 'a style element' => ['<style>body{background:url("https://x.example/p.gif")}</style>', $style];
        yield 'an import' => ['<style>@import "https://x.example/a.css";</style>', 'A receipt loads no style sheet: no "@import".'];
        yield 'an import in an attribute' => ['<div style="@IMPORT x"></div>', 'A receipt loads no style sheet: no "@import".'];
        yield 'a script' => ['<script src="https://x.example/a.js"></script>', $remote];
        yield 'a frame' => ['<iframe src="https://x.example"></iframe>', $remote];
        yield 'an object' => ['<object data="x.swf"></object>', $remote];
        yield 'an embed' => ['<embed src="x.swf">', $remote];
        yield 'a style sheet' => ['<link rel="stylesheet" href="https://x.example/a.css">', $remote];
        yield 'a video' => ['<video poster="https://x.example/p.png"></video>', $remote];
        yield 'an audio' => ['<audio src="a.mp3"></audio>', $remote];
        yield 'a picture' => ['<picture></picture>', $remote];
        yield 'an image button' => ['<form><input type="image" src="x.png"></form>', $remote];
        yield 'a refresh' => ['<meta http-equiv="Refresh" content="0;url=https://x.example">', $remote];
        yield 'a srcset' => ['<img src="cid:logo" srcset="https://x.example/2x.png 2x">', $remote];
        yield 'a campaign link' => ['<a href="https://shop.example/?utm_source=receipt">Shop</a>', $link];
        yield 'a campaign link in uppercase' => ['<a href="https://shop.example/?a=1&UTM_MEDIUM=email">Shop</a>', $link];
        yield 'a bare campaign parameter' => ['<a href="https://shop.example/?utm_">Shop</a>', $link];
        yield 'a click identifier' => ['<a href="https://shop.example/?gclid=abc">Shop</a>', $link];
        yield 'a parameter after a semicolon' => ['<a href="https://shop.example/?a=1;fbclid=abc">Shop</a>', $link];
        yield 'an encoded parameter' => ['<a href="https://shop.example/?%75tm_source=x">Shop</a>', $link];
        yield 'a parameter with spaces' => ['<a href="https://shop.example/?+mc_eid+=x">Shop</a>', $link];
        yield 'a mailer parameter' => ['<a href="https://shop.example/?_hsenc=x#top">Shop</a>', $link];
        yield 'an area' => ['<map><area href="https://shop.example/?mkt_tok=x"></map>', $link];
    }

    #[DataProvider('refused')]
    public function testWhatAReceiptMayNotHoldIsRefused(string $html, string $reason): void
    {
        $this->expectException(NotTransactional::class);
        $this->expectExceptionMessageIs($reason);

        new TransactionalCheck()->check(new RenderedReceipt('Receipt', '', '<html><body>'.$html.'</body></html>', 'Paid'));
    }
}
