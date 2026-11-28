<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Unit\Application;

use Phil\EmailTemplatesBundle\Application\Rendering\HtmlToText;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HtmlToText::class)]
final class HtmlToTextTest extends TestCase
{
    public function testADocumentBecomesReadableText(): void
    {
        $html = '<!doctype html><html><head><title>T</title><style>p{color:red}</style></head><body>'
            .'<div data-text="skip">Preheader</div><p hidden>Hidden</p><script>x()</script>'
            .'<table role="presentation"><tr><td><h1>Hello   Ada</h1></td><td><p>Your <b>order</b> is <a href="https://example.com/o/1">ready</a>.<br>Thanks</p></td></tr></table>'
            .'<ul><li>One</li><li>Two</li></ul><ol><li>A</li><!-- c --><li>B</li></ol>'
            // The HTML5 parser moves the span out of the table, before it, as a browser does.
            .'<table><tr><th>Item</th><th>Qty</th></tr><tr><td>Tea</td><td> 2 </td><span>x</span></tr></table>'
            .'<p><a href="https://example.com">https://example.com</a> <a href="mailto:ada@example.com">ada@example.com</a> <a href="mailto:ada@example.com">Write</a> <a href="#top">Top</a> <a href="tel:+15550100"></a> <img alt=" Logo " src="cid:logo"><img src="x.png"></p>'
            .'<hr><pre>  keep   this
 as is</pre><blockquote>Quote &amp; co</blockquote>  <p>  Bye  </p></body></html>';

        self::assertSame(<<<'TEXT'
            Hello Ada

            Your order is ready (https://example.com/o/1).
            Thanks

            - One
            - Two

            1. A
            2. B

            x

            Item | Qty
            Tea | 2

            https://example.com ada@example.com Write (ada@example.com) Top tel:+15550100 [Logo]

            ----

              keep   this
             as is

            Quote & co

            Bye

            TEXT, HtmlToText::convert($html));
    }

    public function testNothingToRead(): void
    {
        self::assertSame('', HtmlToText::convert("  \n"));
        self::assertSame("Just text\n", HtmlToText::convert('Just   text'));
        self::assertSame("a\n\nb\n", HtmlToText::convert('a<br><br>b'));
        self::assertSame("a\nb\n", HtmlToText::convert('a<br>b'));
    }

    public function testAListOutsideAnyListElement(): void
    {
        self::assertSame("- Alone\n", HtmlToText::convert('<li>Alone</li>'));
    }
}
