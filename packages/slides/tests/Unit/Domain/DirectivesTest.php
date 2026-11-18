<?php

declare(strict_types=1);

namespace Phil\Slides\Tests\Unit\Domain;

use Phil\Slides\Domain\AspectRatio;
use Phil\Slides\Domain\Directives;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Directives::class)]
final class DirectivesTest extends TestCase
{
    public function testNoMetadataGivesTheDefaults(): void
    {
        $directives = Directives::read([], AspectRatio::Standard);

        self::assertNull($directives->settings->title);
        self::assertNull($directives->settings->language);
        self::assertSame([], $directives->settings->classes);
        self::assertFalse($directives->settings->paginate);
        self::assertNull($directives->settings->header);
        self::assertNull($directives->settings->footer);
        self::assertSame(AspectRatio::Standard, $directives->settings->aspectRatio);
        self::assertNull($directives->headingDivider);
        self::assertSame([], $directives->ignored);
        self::assertSame(AspectRatio::Wide, Directives::read([])->settings->aspectRatio);
    }

    public function testEveryDirectiveIsRead(): void
    {
        $directives = Directives::read([
            'title' => '  My talk ',
            'lang' => 'fr-CA',
            'class' => ' lead  invert lead ',
            'paginate' => true,
            'header' => 2026,
            'footer' => 1.5,
            'size' => '4:3',
            'headingDivider' => 3,
        ]);

        self::assertSame('My talk', $directives->settings->title);
        self::assertSame('fr-CA', $directives->settings->language);
        self::assertSame(['lead', 'invert'], $directives->settings->classes);
        self::assertTrue($directives->settings->paginate);
        self::assertSame('2026', $directives->settings->header);
        self::assertSame('1.5', $directives->settings->footer);
        self::assertSame(AspectRatio::Standard, $directives->settings->aspectRatio);
        self::assertSame(3, $directives->headingDivider);
        self::assertSame([], $directives->ignored);
    }

    public function testValuesOfTheWrongKindAreRefusedAndNamed(): void
    {
        $directives = Directives::read([
            'title' => true,
            'lang' => 'not a tag',
            'class' => 'fine bad!class',
            'paginate' => 'yes',
            'header' => ['not', 'text'],
            'footer' => str_repeat('a', Directives::MAX_TEXT + 1),
            'size' => '21:9',
            'headingDivider' => 7,
            'theme' => 'gaia',
            0 => 'numbered',
        ]);

        self::assertSame(['title', 'lang', 'class', 'paginate', 'header', 'footer', 'size', 'headingDivider', 'theme', '0'], $directives->ignored);
        self::assertNull($directives->settings->title);
        self::assertSame([], $directives->settings->classes);
        self::assertFalse($directives->settings->paginate);
        self::assertSame(AspectRatio::Wide, $directives->settings->aspectRatio);
        self::assertNull($directives->headingDivider);
    }

    public function testTheLimitsAreInclusive(): void
    {
        $directives = Directives::read([
            'title' => str_repeat('é', Directives::MAX_TEXT),
            'class' => implode(' ', array_map(static fn (int $index): string => 'c'.$index, range(1, Directives::MAX_CLASSES))),
            'headingDivider' => 1,
            'lang' => 123,
            'size' => 43,
        ]);

        self::assertSame(str_repeat('é', 200), $directives->settings->title);
        self::assertCount(16, $directives->settings->classes);
        self::assertSame(1, $directives->headingDivider);
        self::assertSame(6, Directives::read(['headingDivider' => 6])->headingDivider);
        self::assertSame(['lang', 'size'], $directives->ignored);
        self::assertSame(['class'], Directives::read(['class' => implode(' ', range(1, 17))])->ignored);
        self::assertSame(['class', 'headingDivider'], Directives::read(['class' => '   ', 'headingDivider' => 0])->ignored);
        self::assertSame(['class'], Directives::read(['class' => 12])->ignored);
        self::assertSame([str_repeat('c', 64)], Directives::read(['class' => str_repeat('c', 64)])->settings->classes);
        self::assertSame(['class'], Directives::read(['class' => str_repeat('c', 65)])->ignored);
        self::assertSame(['headingDivider'], Directives::read(['headingDivider' => '2'])->ignored);
        self::assertSame(['header'], Directives::read(['header' => '   '])->ignored);
        self::assertSame(['a', 'b', 'c', 'd', 'e', 'f'], Directives::read(['class' => "a\tb\nc\rd\fe\vf"])->settings->classes);
    }
}
