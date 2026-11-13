<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Unit\Domain;

use Phil\CommentsBundle\Domain\Anchoring\Passage;
use Phil\CommentsBundle\Domain\Anchoring\Reanchoring;
use Phil\CommentsBundle\Domain\ValueObject\Anchor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Reanchoring::class)]
#[CoversClass(Passage::class)]
final class ReanchoringTest extends TestCase
{
    public function testAPassageStillInPlaceKeepsItsPlace(): void
    {
        $anchor = Anchor::of('b1', 'text', 4, 'cat', 'the ', ' sat');

        self::assertEquals($anchor, Reanchoring::locate($anchor, [new Passage('b1', 'text', 'the cat sat')]));
    }

    public function testAPassageMovedByAnEditIsFoundByItsContext(): void
    {
        $found = Reanchoring::locate(Anchor::of('b1', 'text', 4, 'cat', 'the ', ' sat'), [new Passage('b1', 'text', 'the cat ran, the cat sat')]);

        self::assertSame([17, 20, 'the cat ran, the ', ' sat'], [$found?->start, $found?->end, $found?->prefix, $found?->suffix]);
        self::assertSame(1, Reanchoring::locate(Anchor::of('b1', 'text', 9, 'cat', 'x', ''), [new Passage('b1', 'text', 'xcat ycat')])?->start);
    }

    public function testACharacterOnlyPartlySharedByTheContextDoesNotCount(): void
    {
        // Before the first `x`, `ĩ` ends with the last byte of `é`; after the second, `ê` starts with its first byte:
        // neither shares a character, so the place nearest the old offset wins.
        self::assertSame(10, Reanchoring::locate(Anchor::of(null, null, 10, 'x', 'é'), [new Passage(null, null, 'ĩxabcdefghx')])?->start);
        self::assertSame(0, Reanchoring::locate(Anchor::of(null, null, 0, 'x', '', 'é'), [new Passage(null, null, 'xabcdefghxê')])?->start);
    }

    public function testSharedContextsAreCountedInCharacters(): void
    {
        // `é` shared is one character, two bytes; `ab` two characters: the place sharing `ab` wins, though farther.
        self::assertSame(6, Reanchoring::locate(Anchor::of(null, null, 1, 'x', 'é', 'ab'), [new Passage(null, null, 'éx....xab')])?->start);
        self::assertSame(7, Reanchoring::locate(Anchor::of(null, null, 0, 'x', 'ab', 'é'), [new Passage(null, null, 'xé...abx')])?->start);
    }

    public function testInItsFieldTheNearestPlaceWinsAmongEqualContextsTheFirstOnATie(): void
    {
        self::assertSame(8, Reanchoring::locate(Anchor::of('b1', 'text', 9, 'x'), [new Passage('b1', 'text', 'x.......x.x')])?->start);
        self::assertSame(4, Reanchoring::locate(Anchor::of('b1', 'text', 5, 'x'), [new Passage('b1', 'text', 'x...x.x')])?->start);
        self::assertSame(0, Reanchoring::locate(Anchor::of('b1', 'text', 2, 'x'), [new Passage('b1', 'text', 'x...x')])?->start);
        self::assertSame(0, Reanchoring::locate(Anchor::of('b9', 'text', 2, 'x'), [new Passage('b1', 'text', 'x...x')])?->start);
    }

    public function testItsFieldComesBeforeItsBlockItsBlockBeforeTheRest(): void
    {
        $passages = [new Passage('b0', 'text', 'cat'), new Passage('b1', 'caption', 'cat'), new Passage('b1', 'text', 'a cat')];
        $anchor = Anchor::of('b1', 'text', 0, 'cat');

        self::assertSame(['b1', 'text', 2], [Reanchoring::locate($anchor, $passages)?->block, Reanchoring::locate($anchor, $passages)?->field, Reanchoring::locate($anchor, $passages)?->start]);
        self::assertSame('caption', Reanchoring::locate($anchor, \array_slice($passages, 0, 2))?->field);
        self::assertSame('b0', Reanchoring::locate($anchor, \array_slice($passages, 0, 1))?->block);
        self::assertSame('b0', Reanchoring::locate(Anchor::of(null, null, 0, 'cat'), [new Passage('b0', 'text', 'cat'), new Passage(null, 'x', 'cat')])?->block);
        self::assertSame('b2', Reanchoring::locate(Anchor::of('b1', 'text', 0, 'cat', '', 'z'), [new Passage('b0', 'text', 'cat'), new Passage('b2', 'text', 'catz')])?->block);
        self::assertSame('b0', Reanchoring::locate(Anchor::of('b1', 'text', 0, 'cat'), [new Passage('b0', 'text', 'cat'), new Passage('b2', 'text', 'cat')])?->block);
    }

    public function testAPassageThatOccursNowhereIsGone(): void
    {
        self::assertNull(Reanchoring::locate(Anchor::of('b1', 'text', 0, 'dog'), [new Passage('b1', 'text', 'cat')]));
        self::assertNull(Reanchoring::locate(Anchor::of('b1', 'text', 0, 'dog'), []));
    }

    public function testCharactersAreCountedOnce(): void
    {
        $found = Reanchoring::locate(Anchor::of('b1', 'text', 0, 'b', '😀'), [new Passage('b1', 'text', '😀😀b😀')]);
        self::assertSame([2, 3, '😀😀', '😀'], [$found?->start, $found?->end, $found?->prefix, $found?->suffix]);

        self::assertSame([2, 5], [Reanchoring::locate(Anchor::of('b1', 'text', 0, 'b', 'éé'), [new Passage('b1', 'text', 'éébéébé')])?->start, Reanchoring::locate(Anchor::of('b1', 'text', 0, 'b', 'éébéé'), [new Passage('b1', 'text', 'éébéébé')])?->start]);
        $found = Reanchoring::locate(Anchor::of('b1', 'text', 0, 'é'), [new Passage('b1', 'text', 'aéb')]);
        self::assertSame([1, 2, 'a', 'b'], [$found?->start, $found?->end, $found?->prefix, $found?->suffix]);
        self::assertSame(0, Reanchoring::locate(Anchor::of('b1', 'text', 0, 'aa'), [new Passage('b1', 'text', 'aaa')])?->start);
        self::assertSame(1, Reanchoring::locate(Anchor::of('b1', 'text', 1, 'aa'), [new Passage('b1', 'text', 'aaa')])?->start);
        self::assertSame(1, Reanchoring::locate(Anchor::of('b1', 'text', 1, 'a'), [new Passage('b1', 'text', 'aa')])?->start);
    }

    public function testTheContextFoundKeeps32Characters(): void
    {
        $text = str_repeat('y', 40).'a'.str_repeat('z', 40);
        $found = Reanchoring::locate(Anchor::of('b1', 'text', 0, 'a', str_repeat('x', 40)), [new Passage('b1', 'text', $text)]);

        self::assertSame([40, str_repeat('y', 32), str_repeat('z', 32)], [$found?->start, $found?->prefix, $found?->suffix]);
        self::assertSame(['yy', 'zz'], [Reanchoring::locate(Anchor::of('b1', 'text', 0, 'a'), [new Passage('b1', 'text', 'yyazz')])?->prefix, Reanchoring::locate(Anchor::of('b1', 'text', 0, 'a'), [new Passage('b1', 'text', 'yyazz')])?->suffix]);
    }

    public function testTheContextOnBothSidesCounts(): void
    {
        $text = 'p a q · r a s';

        self::assertSame(2, Reanchoring::locate(Anchor::of('b1', 'text', 10, 'a', 'p ', ' q'), [new Passage('b1', 'text', $text)])?->start);
        self::assertSame(2, Reanchoring::locate(Anchor::of('b1', 'text', 10, 'a', 'p ', ''), [new Passage('b1', 'text', $text)])?->start);
        self::assertSame(2, Reanchoring::locate(Anchor::of('b1', 'text', 10, 'a', '', ' q'), [new Passage('b1', 'text', $text)])?->start);
        self::assertSame(10, Reanchoring::locate(Anchor::of('b1', 'text', 0, 'a', 'r ', ' s'), [new Passage('b1', 'text', $text)])?->start);
        self::assertSame(10, Reanchoring::locate(Anchor::of('b1', 'text', 0, 'a', 'x r ', ' s y'), [new Passage('b1', 'text', $text)])?->start);
    }

    public function testTheContextOfWideCharactersIsReadWhole(): void
    {
        foreach (['😀', 'é', '€'] as $wide) {
            $text = 'a'.str_repeat($wide, 40).'cat'.str_repeat($wide, 40).'b';
            $found = Reanchoring::locate(Anchor::of(null, null, 0, 'cat'), [new Passage(null, null, $text)]);

            self::assertSame([41, str_repeat($wide, 32), str_repeat($wide, 32)], [$found?->start, $found?->prefix, $found?->suffix], $wide);
        }

        $found = Reanchoring::locate(Anchor::of(null, null, 0, 'cat'), [new Passage(null, null, '😀😀cat😀😀')]);
        self::assertSame([2, '😀😀', '😀😀'], [$found?->start, $found?->prefix, $found?->suffix]);
    }

    public function testAPassageThatDisappearedMovesToTheOccurrenceWhoseContextIsClosestTheFirstOnATie(): void
    {
        $anchor = Anchor::of('b1', 'text', 4, 'cat', 'the ', ' sat on');
        $passages = [new Passage('b1', 'text', 'a dog sat'), new Passage('b2', 'text', 'one cat ran, the cat sat still'), new Passage('b3', 'text', 'the cat sat on')];

        self::assertSame(['b3', 4], [Reanchoring::locate($anchor, $passages)?->block, Reanchoring::locate($anchor, $passages)?->start]);
        self::assertSame(['b2', 4], [Reanchoring::locate(Anchor::of('b1', 'text', 4, 'cat', 'zz', 'zz'), $passages)?->block, Reanchoring::locate(Anchor::of('b1', 'text', 4, 'cat', 'zz', 'zz'), $passages)?->start]);
    }
}
