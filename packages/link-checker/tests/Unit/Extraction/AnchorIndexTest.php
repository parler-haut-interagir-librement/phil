<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Tests\Unit\Extraction;

use Phil\LinkChecker\Extraction\AnchorIndex;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AnchorIndex::class)]
final class AnchorIndexTest extends TestCase
{
    public function testAnIdOrTheNameOfAnAnchorIsATarget(): void
    {
        $index = AnchorIndex::fromHtml('<h2 id="intro">Intro</h2><a name="legacy"></a><div name="not-a-target"></div><p id="café">x</p><span id="">e</span>', 1000);

        self::assertTrue($index->has('intro'));
        self::assertTrue($index->has('legacy'));
        self::assertFalse($index->has('not-a-target'));
        self::assertFalse($index->has('Intro'));
        self::assertFalse($index->has('missing'));
        self::assertTrue($index->has('café'));
        self::assertTrue($index->has('caf%C3%A9'));
    }

    public function testTheIdOfAnAnchorAndTheNameOfAnotherElementWithAnId(): void
    {
        $index = AnchorIndex::fromHtml('<a id="anchor-id"></a><input id="field" name="field-name">', 1000);

        self::assertTrue($index->has('anchor-id'));
        self::assertTrue($index->has('field'));
        self::assertFalse($index->has('field-name'));
    }

    public function testAnEscapedIdIsFoundAsWritten(): void
    {
        $index = AnchorIndex::fromHtml('<p id="a%20b">x</p>', 1000);

        self::assertTrue($index->has('a%20b'));
        self::assertFalse($index->has('a b'));
    }

    public function testTheTopOfThePageIsAlwaysATarget(): void
    {
        $index = AnchorIndex::fromHtml('<p>nothing</p>', 1000);

        self::assertTrue($index->has(''));
        self::assertTrue($index->has('top'));
        self::assertTrue($index->has('TOP'));
        self::assertFalse($index->has('topic'));
    }

    public function testAPageReadInPartFindsEveryFragment(): void
    {
        $html = '<p id="a">x</p><p id="b">y</p>';

        $cut = AnchorIndex::fromHtml($html, \strlen($html) - 1);
        self::assertTrue($cut->has('anything'));

        $exact = AnchorIndex::fromHtml($html, \strlen($html));
        self::assertTrue($exact->has('b'));
        self::assertFalse($exact->has('anything'));

        $incomplete = AnchorIndex::fromHtml($html, 1000, false);
        self::assertTrue($incomplete->has('anything'));
    }
}
