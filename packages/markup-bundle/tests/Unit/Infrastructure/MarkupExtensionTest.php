<?php

declare(strict_types=1);

namespace Phil\MarkupBundle\Tests\Unit\Infrastructure;

use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\Level;
use Phil\Markup\MarkedUpText;
use Phil\Markup\Markup;
use Phil\Markup\MarkupFormat;
use Phil\MarkupBundle\Infrastructure\Conversion\UjoConverter;
use Phil\MarkupBundle\Infrastructure\Format\MarkupFormats;
use Phil\MarkupBundle\Infrastructure\Format\UnavailableFormat;
use Phil\MarkupBundle\Infrastructure\Twig\MarkupExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarkupExtension::class)]
final class MarkupExtensionTest extends TestCase
{
    public function testTheMarkupFilter(): void
    {
        $extension = $this->extension();

        self::assertSame('', $extension->markup(null));
        self::assertSame('<h2 id="a">a</h2>', $extension->markup('## a', 'markdown', 'all'));
        self::assertSame('a', $extension->markup('## a'));
        self::assertSame('<b>a</b>', $extension->markup('[b]a[/b]', 'bbcode', 'basic'));
        self::assertSame('a', $extension->markup('[h2]a[/h2]', 'bbcode', 'all'));

        $this->expectException(UnavailableFormat::class);
        $extension->markup('a', 'html');
    }

    public function testThePurifyFilter(): void
    {
        $extension = $this->extension();

        self::assertSame(['', ''], [$extension->purify(null), $extension->purify(null, 'all')]);
        self::assertSame('a', $extension->purify('<h2>a</h2>'));
        self::assertSame('<h2>a</h2>', $extension->purify('<h2>a</h2>', 'all'));
        self::assertSame('<h2>a</h2>', $extension->purify(new MarkedUpText('', MarkupFormat::Markdown, '<h2>a</h2>'), 'all'));
        self::assertSame('a', $extension->purify(new MarkedUpText('', MarkupFormat::BBCode, '<h2>a</h2>'), 'all'));
        self::assertSame('<b>a</b>', $extension->purify(new MarkedUpText('', MarkupFormat::Html, '<p><b>a</b></p>'), 'basic'));
    }

    public function testTheUjoFilter(): void
    {
        $extension = $this->extension();

        self::assertSame('{"blocks":[]}', $extension->ujo(null));
        self::assertSame('{"blocks":[]}', $extension->ujo(null, 'html'));
        self::assertSame('{"blocks":[{"id":"b1","type":"paragraph","data":{"text":"<b>a</b>"}}]}', $extension->ujo('**a**'));
        self::assertSame('{"blocks":[{"id":"b1","type":"paragraph","data":{"text":"<b>a</b>"}}]}', $extension->ujo('[b]a[/b]', 'bbcode', 'basic'));
        self::assertStringStartsWith('{"blocks":[{"id":"b1","type":"paragraph"', $extension->ujo("[html]\n<i>x</i>\n[/html]", 'bbcode', 'all'));

        $this->expectException(UnavailableFormat::class);
        $extension->ujo('a', 'html');
    }

    private function extension(): MarkupExtension
    {
        $purifier = SanitizerPurifier::defaultPolicies();
        $formats = new MarkupFormats(['markdown', 'bbcode'], 'markdown', Level::Forum, ['bbcode' => Level::Forum]);

        return new MarkupExtension(new Markup(new DefaultCompiler(), $purifier), $purifier, $formats, new UjoConverter(new DefaultCompiler(), $formats));
    }
}
