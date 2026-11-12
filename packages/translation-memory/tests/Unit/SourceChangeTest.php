<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Tests\Unit;

use Phil\TextDiff\TextDiff;
use Phil\TranslationMemory\Segment;
use Phil\TranslationMemory\SourceChange;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceChange::class)]
final class SourceChangeTest extends TestCase
{
    public function testShowsTheWordsThatChangedInTheSourceOfAStaleSegment(): void
    {
        $segment = new Segment('k', 'The <b>red</b> door')->translate('La porte rouge')->withSource('The <b>blue</b> door & more');

        self::assertSame('The <del>red</del><ins>blue</ins> door<ins> &amp; more</ins>', new SourceChange()->of($segment));
        self::assertSame('The <del>red door</del><ins>blue door &amp; more</ins>', new SourceChange(new TextDiff(0))->of($segment));
    }

    public function testShowsNothingWithoutAnEarlierSource(): void
    {
        self::assertNull(new SourceChange()->of(new Segment('k', 'Hello')));
        self::assertNull(new SourceChange()->of(new Segment('k', 'Hello')->translate('Bonjour')));
        self::assertNull(new SourceChange()->of(new Segment('k', 'Hello <b>you</b>')->translate('Salut')->withSource('Hello <i>you</i>')));
    }
}
