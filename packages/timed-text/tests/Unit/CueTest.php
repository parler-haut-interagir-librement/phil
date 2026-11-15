<?php

declare(strict_types=1);

namespace Phil\TimedText\Tests\Unit;

use Phil\TimedText\Cue;
use Phil\TimedText\CueSettings;
use Phil\TimedText\Exception\InvalidCue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Cue::class)]
final class CueTest extends TestCase
{
    public function testItCarriesItsValues(): void
    {
        $settings = new CueSettings(['align' => 'start']);
        $cue = new Cue(1_000, 2_500, '<v Alice>Hello &amp; welcome', 'intro', $settings);

        self::assertSame(1_000, $cue->start);
        self::assertSame(2_500, $cue->end);
        self::assertSame(1_500, $cue->duration());
        self::assertSame('intro', $cue->id);
        self::assertSame($settings, $cue->settings);
        self::assertSame('Hello & welcome', $cue->plainText());
        self::assertSame('Alice', $cue->speaker());
        self::assertTrue(new Cue(0, 0, '')->settings->isEmpty());
    }

    /**
     * @return iterable<string, array{int, int, string, ?string, string}>
     */
    public static function invalidCues(): iterable
    {
        yield 'end before start' => [2, 1, '', null, 'A cue ends at 1 ms, before its start at 2 ms.'];
        yield 'negative start' => [-1, 1, '', null, '-1 ms is not an instant'];
        yield 'end out of range' => [0, 36_000_000_000, '', null, '36000000000 ms is not an instant'];
        yield 'empty line' => [0, 1, "a\n\nb", null, 'The text of a cue holds no carriage return'];
        yield 'trailing line feed' => [0, 1, "a\n", null, 'The text of a cue holds no carriage return'];
        yield 'leading line feed' => [0, 1, "\na", null, 'The text of a cue holds no carriage return'];
        yield 'carriage return' => [0, 1, "a\rb", null, 'The text of a cue holds no carriage return'];
        yield 'arrow' => [0, 1, 'a --> b', null, 'The text of a cue holds no carriage return'];
        yield 'empty identifier' => [0, 1, '', '', 'The identifier of a cue is one line'];
        yield 'identifier on two lines' => [0, 1, '', "a\nb", 'The identifier of a cue is one line'];
        yield 'identifier with a carriage return' => [0, 1, '', "a\rb", 'The identifier of a cue is one line'];
        yield 'identifier with an arrow' => [0, 1, '', 'a-->b', 'The identifier of a cue is one line'];
        yield 'identifier as a note' => [0, 1, '', 'NOTE this', 'does not open as a NOTE, STYLE or REGION block'];
        yield 'identifier as a lone note' => [0, 1, '', 'NOTE', 'does not open as a NOTE'];
        yield 'identifier as a style' => [0, 1, '', 'STYLE ', 'does not open as a NOTE'];
        yield 'identifier as a region' => [0, 1, '', 'REGION', 'does not open as a NOTE'];
    }

    #[DataProvider('invalidCues')]
    public function testItRefusesWhatWouldBreakABlock(int $start, int $end, string $text, ?string $id, string $message): void
    {
        $this->expectException(InvalidCue::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Cue($start, $end, $text, $id);
    }

    public function testItAcceptsIdentifiersThatOnlyLookLikeBlocks(): void
    {
        self::assertSame('NOTES', new Cue(0, 1, '', 'NOTES')->id);
        self::assertSame('STYLE sheet', new Cue(0, 1, '', 'STYLE sheet')->id);
        self::assertSame('a REGION', new Cue(0, 1, '', 'a REGION')->id);
        self::assertSame("a\nb", new Cue(0, 1, "a\nb")->text);
    }

    public function testItSaysWhichTextsItCarries(): void
    {
        self::assertTrue(Cue::isWritableText(''));
        self::assertTrue(Cue::isWritableText("one\ntwo"));
        self::assertFalse(Cue::isWritableText("\n"));
        self::assertFalse(Cue::isWritableText('->>-->'));
    }

    public function testItSaysWhenItIsShown(): void
    {
        $cue = new Cue(1_000, 2_000, '');

        self::assertTrue($cue->overlaps(1_000, 1_000));
        self::assertTrue($cue->overlaps(1_999, 1_999));
        self::assertFalse($cue->overlaps(2_000, 2_000));
        self::assertFalse($cue->overlaps(999, 999));
        self::assertTrue($cue->overlaps(0, 1_001));
        self::assertFalse($cue->overlaps(0, 1_000));
        self::assertTrue($cue->overlaps(1_999, 3_000));
        self::assertFalse($cue->overlaps(2_000, 3_000));

        $instant = new Cue(1_000, 1_000, '');
        self::assertTrue($instant->overlaps(1_000, 1_000));
        self::assertFalse($instant->overlaps(999, 999));
        self::assertFalse($instant->overlaps(1_001, 1_001));
    }

    public function testItChangesIntoNewCues(): void
    {
        $cue = new Cue(1_000, 2_000, 'a', 'id', new CueSettings(['align' => 'end']));
        $moved = $cue->shifted(500);
        $written = $cue->withText('b');

        self::assertSame([1_500, 2_500, 'a', 'id', 'end'], [$moved->start, $moved->end, $moved->text, $moved->id, $moved->settings->get('align')]);
        self::assertSame(['b', 'id', 'end', 1_000], [$written->text, $written->id, $written->settings->get('align'), $written->start]);
        self::assertSame([500, 1_500], [$cue->shifted(-500)->start, $cue->shifted(-500)->end]);
    }

    public function testItSplitsAtAnInstantInside(): void
    {
        [$first, $second] = new Cue(1_000, 3_000, 'a', 'id', new CueSettings(['align' => 'end']))->splitAt(2_000);

        self::assertSame([1_000, 2_000, 'a', 'id', 'end'], [$first->start, $first->end, $first->text, $first->id, $first->settings->get('align')]);
        self::assertSame([2_000, 3_000, 'a', null, 'end'], [$second->start, $second->end, $second->text, $second->id, $second->settings->get('align')]);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function outsideInstants(): iterable
    {
        yield 'start' => [1_000];
        yield 'end' => [3_000];
        yield 'before' => [999];
        yield 'after' => [3_001];
    }

    #[DataProvider('outsideInstants')]
    public function testItSplitsOnlyInside(int $at): void
    {
        $this->expectException(InvalidCue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('%d ms is not inside the cue from 1000 to 3000 ms.', $at));

        new Cue(1_000, 3_000, '')->splitAt($at);
    }

    public function testItMergesWithTheNext(): void
    {
        $first = new Cue(1_000, 2_000, 'a', 'one', new CueSettings(['align' => 'end']));
        $merged = $first->mergedWith(new Cue(1_500, 3_000, 'b', 'two'));

        self::assertSame([1_000, 3_000, "a\nb", 'one', 'end'], [$merged->start, $merged->end, $merged->text, $merged->id, $merged->settings->get('align')]);
        self::assertSame('a b', $first->mergedWith(new Cue(2_000, 2_500, 'b'), ' ')->text);
        self::assertSame('a', $first->mergedWith(new Cue(2_000, 2_500, ''))->text);
        self::assertSame('b', new Cue(0, 1, '')->mergedWith(new Cue(2_000, 2_500, 'b'))->text);

        $earlier = new Cue(5_000, 6_000, 'a')->mergedWith(new Cue(1_000, 2_000, 'b'));
        self::assertSame([1_000, 6_000], [$earlier->start, $earlier->end]);
    }
}
