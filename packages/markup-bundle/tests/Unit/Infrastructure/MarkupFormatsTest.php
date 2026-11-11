<?php

declare(strict_types=1);

namespace Phil\MarkupBundle\Tests\Unit\Infrastructure;

use Phil\Markup\Exception\MarkupException;
use Phil\Markup\Level;
use Phil\Markup\MarkupFormat;
use Phil\MarkupBundle\Infrastructure\Format\MarkupFormats;
use Phil\MarkupBundle\Infrastructure\Format\UnavailableFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ValueError;

#[CoversClass(MarkupFormats::class)]
#[CoversClass(UnavailableFormat::class)]
final class MarkupFormatsTest extends TestCase
{
    public function testTheEnabledFormats(): void
    {
        $formats = new MarkupFormats(['bbcode', 'text'], 'text');

        self::assertSame(['bbcode', 'text'], $formats->enabled());
        self::assertTrue($formats->isEnabled(MarkupFormat::BBCode));
        self::assertTrue($formats->isEnabled('bbcode'));
        self::assertFalse($formats->isEnabled('markdown'));
        self::assertSame('text', $formats->format());
        self::assertSame('bbcode', $formats->format('bbcode'));
        self::assertSame('bbcode', $formats->format(MarkupFormat::BBCode));
    }

    /**
     * A format an add-on brings is enabled by its name, and keeps the label it gives.
     */
    public function testTheFormatOfAnAddOn(): void
    {
        $formats = new MarkupFormats(['shout', 'text'], 'shout', Level::All, ['shout' => Level::Forum], ['shout' => 'Shout']);

        self::assertSame('shout', $formats->format());
        self::assertTrue($formats->isEnabled('shout'));
        self::assertSame('Shout', $formats->label('shout'));
        self::assertNull($formats->label('text'));
        self::assertSame(Level::Forum, $formats->level('shout'));
        self::assertSame(Level::Forum, $formats->ceiling('shout'));
    }

    public function testAFormatNotEnabledIsRefused(): void
    {
        $formats = new MarkupFormats(['bbcode', 'text'], 'text');

        foreach (['markdown' => 'markdown', 'latex' => 'latex', 'ujo' => MarkupFormat::Ujo] as $name => $format) {
            try {
                $formats->format($format);
                self::fail($name);
            } catch (UnavailableFormat $unavailableFormat) {
                self::assertInstanceOf(MarkupException::class, $unavailableFormat);
                self::assertSame(\sprintf('The format "%s" is not one of the enabled formats: bbcode, text.', $name), $unavailableFormat->getMessage());
            }
        }
    }

    public function testTheLevels(): void
    {
        $formats = new MarkupFormats(['bbcode', 'markdown'], 'markdown', Level::Forum, ['bbcode' => Level::Forum, 'text' => Level::Basic]);

        self::assertSame(Level::Forum, $formats->level(MarkupFormat::Markdown));
        self::assertSame(Level::All, $formats->level('markdown', 'all'));
        self::assertSame(Level::Basic, $formats->level('markdown', Level::Basic));
        self::assertSame(Level::Forum, $formats->level('bbcode', 'all'));
        self::assertSame(Level::Forum, $formats->level('bbcode', 'forum'));
        self::assertSame(Level::Basic, $formats->level('bbcode', 'basic'));
        self::assertSame(Level::Basic, $formats->level('text'));
        self::assertSame(Level::All, $formats->level(null, 'all'));
        self::assertSame(Level::Forum, $formats->level(null));
        self::assertSame(Level::Forum, $formats->ceiling(MarkupFormat::BBCode));
        self::assertSame(Level::All, $formats->ceiling('markdown'));
        self::assertSame(Level::All, new MarkupFormats(['text'], 'text')->level('text'));

        $this->expectException(ValueError::class);
        $formats->level('markdown', 'none');
    }
}
