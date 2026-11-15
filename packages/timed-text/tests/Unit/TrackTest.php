<?php

declare(strict_types=1);

namespace Phil\TimedText\Tests\Unit;

use Phil\TimedText\Cue;
use Phil\TimedText\Exception\InvalidCue;
use Phil\TimedText\Note;
use Phil\TimedText\Region;
use Phil\TimedText\Track;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Track::class)]
#[CoversClass(Note::class)]
final class TrackTest extends TestCase
{
    public function testItDescribesItsCues(): void
    {
        $track = new Track([new Cue(0, 1_000, 'a'), new Cue(500, 4_000, 'b'), new Cue(2_000, 3_000, 'c')]);

        self::assertSame(3, $track->count());
        self::assertFalse($track->isEmpty());
        self::assertSame(4_000, $track->duration());
        self::assertSame(['a', 'b'], $this->texts($track->at(500)));
        self::assertSame(['b'], $this->texts($track->at(1_000)));
        self::assertSame(['b', 'c'], $this->texts($track->between(1_000, 2_001)));
        self::assertSame([], new Track()->at(0));
        self::assertTrue(new Track()->isEmpty());
        self::assertSame(0, new Track()->duration());
        self::assertSame(0, new Track()->count());
    }

    public function testItRefusesAHeaderOrAStyleThatWouldBreakTheFile(): void
    {
        $builds = [
            static fn (): Track => new Track(header: "a\nb"),
            static fn (): Track => new Track(header: "a\rb"),
            static fn (): Track => new Track(header: 'a-->'),
            static fn (): Track => new Track(styles: ['']),
            static fn (): Track => new Track(styles: ["a\n\nb"]),
            static fn (): Track => new Track(styles: ['a --> b']),
        ];

        foreach ($builds as $index => $build) {
            try {
                $build();
                self::fail('A track was built by the closure '.$index);
            } catch (InvalidCue $exception) {
                self::assertMatchesRegularExpression('/^(The header of a track is one line|A style is not empty)/', $exception->getMessage());
            }
        }

        self::assertSame(["a {\n}"], new Track(styles: ["a {\n}"])->styles);
    }

    public function testItChangesIntoNewTracks(): void
    {
        $region = new Region('r');
        $note = new Note('n', 0);
        $track = new Track([new Cue(0, 1, 'a')], [$region], ['s'], [$note], 'header');
        $changed = $track->withCues([new Cue(5, 6, 'b')]);
        $bare = $track->withoutStyles();

        self::assertSame(['b'], $this->texts($changed->cues));
        self::assertSame([[$region], ['s'], [$note], 'header'], [$changed->regions, $changed->styles, $changed->notes, $changed->header]);
        self::assertSame([['a'], [$region], [], [$note], 'header'], [$this->texts($bare->cues), $bare->regions, $bare->styles, $bare->notes, $bare->header]);
    }

    public function testItSortsByStartThenEndKeepingTies(): void
    {
        $track = new Track([new Cue(5, 9, 'late'), new Cue(1, 4, 'long'), new Cue(1, 2, 'short'), new Cue(1, 4, 'tie'), new Cue(0, 9, 'first')], [new Region('r')], ['s'], [new Note('n')], 'h');
        $sorted = $track->sorted();

        self::assertSame(['first', 'short', 'long', 'tie', 'late'], $this->texts($sorted->cues));
        self::assertSame(['r', 's', 'n', 'h'], [$sorted->regions[0]->id, $sorted->styles[0], $sorted->notes[0]->text, $sorted->header]);
    }

    public function testItShiftsLater(): void
    {
        $track = new Track([new Cue(0, 0, 'instant'), new Cue(1_000, 2_000, 'a')], [new Region('r')], ['s'], [new Note('n', 1_000), new Note('end')], 'h');
        $shifted = $track->shifted(500);

        self::assertSame([[500, 500], [1_500, 2_500]], $this->times($shifted->cues));
        self::assertSame([1_500, null], [$shifted->notes[0]->at, $shifted->notes[1]->at]);
        self::assertSame(['r', 's', 'h'], [$shifted->regions[0]->id, $shifted->styles[0], $shifted->header]);
        self::assertSame([[0, 0], [1_000, 2_000]], $this->times($track->shifted(0)->cues));
    }

    public function testItShiftsEarlierDroppingWhatEndsBeforeTheStart(): void
    {
        $track = new Track([new Cue(0, 1_000, 'gone'), new Cue(500, 1_500, 'clipped'), new Cue(1_000, 1_000, 'instant'), new Cue(2_000, 3_000, 'kept'), new Cue(900, 1_001, 'last millisecond')], notes: [new Note('n', 500)]);
        $shifted = $track->shifted(-1_000);

        self::assertSame(['clipped', 'instant', 'kept', 'last millisecond'], $this->texts($shifted->cues));
        self::assertSame([[0, 500], [0, 0], [1_000, 2_000], [0, 1]], $this->times($shifted->cues));
        self::assertSame(0, $shifted->notes[0]->at);
    }

    public function testItMergesTwoTracks(): void
    {
        $first = new Track([new Cue(0, 1_000, 'a'), new Cue(3_000, 4_000, 'c')], [new Region('one', ['lines' => '2'])], ['s1', 'shared'], [new Note('n1')], 'first');
        $second = new Track([new Cue(1_000, 2_000, 'b')], [new Region('one', ['lines' => '9']), new Region('two')], ['shared', 's2'], [new Note('n2')], 'second');
        $merged = $first->mergedWith($second);

        self::assertSame(['a', 'b', 'c'], $this->texts($merged->cues));
        self::assertSame(['one', 'two'], array_map(static fn (Region $region): string => $region->id, $merged->regions));
        self::assertSame('2', $merged->regions[0]->settings['lines']);
        self::assertSame(['s1', 'shared', 's2'], $merged->styles);
        self::assertSame(['n1', 'n2'], array_map(static fn (Note $note): string => $note->text, $merged->notes));
        self::assertSame('first', $merged->header);
    }

    public function testItSplitsAtAnInstant(): void
    {
        $track = new Track(
            [new Cue(0, 1_000, 'before'), new Cue(500, 2_000, 'across'), new Cue(1_000, 1_000, 'instant'), new Cue(1_000, 3_000, 'after')],
            [new Region('r')],
            ['s'],
            [new Note('early', 0), new Note('late', 1_000), new Note('end')],
            'h',
        );

        [$first, $second] = $track->splitAt(1_000);

        self::assertSame(['before', 'across'], $this->texts($first->cues));
        self::assertSame([[0, 1_000], [500, 1_000]], $this->times($first->cues));
        self::assertSame(['across', 'instant', 'after'], $this->texts($second->cues));
        self::assertSame([[1_000, 2_000], [1_000, 1_000], [1_000, 3_000]], $this->times($second->cues));
        self::assertSame(['early'], array_map(static fn (Note $note): string => $note->text, $first->notes));
        self::assertSame(['late', 'end'], array_map(static fn (Note $note): string => $note->text, $second->notes));
        self::assertSame(['r', 's', 'h'], [$first->regions[0]->id, $first->styles[0], $first->header]);
        self::assertSame(['r', 's', 'h'], [$second->regions[0]->id, $second->styles[0], $second->header]);

        [, $rebased] = $track->splitAt(1_000, true);

        self::assertSame([[0, 1_000], [0, 0], [0, 2_000]], $this->times($rebased->cues));
        self::assertSame([0, null], [$rebased->notes[0]->at, $rebased->notes[1]->at]);
    }

    public function testItSplitsOnlyAtAnInstant(): void
    {
        $this->expectException(InvalidCue::class);

        new Track()->splitAt(-1);
    }

    public function testANoteIsAnchoredToAnInstant(): void
    {
        $note = new Note("one\ntwo", 1_000);

        self::assertSame("one\ntwo", $note->text);
        self::assertSame(1_000, $note->at);
        self::assertSame(400, $note->shifted(-600)->at);
        self::assertSame(0, $note->shifted(-2_000)->at);
        $free = new Note('');
        self::assertSame($free, $free->shifted(5));
    }

    /**
     * @return iterable<string, array{string, ?int}>
     */
    public static function invalidNotes(): iterable
    {
        yield 'empty line' => ["a\n\nb", null];
        yield 'arrow' => ['a --> b', null];
        yield 'negative anchor' => ['a', -1];
    }

    #[DataProvider('invalidNotes')]
    public function testItRefusesANoteThatWouldBreakTheFile(string $text, ?int $at): void
    {
        $this->expectException(InvalidCue::class);

        new Note($text, $at);
    }

    /**
     * @param list<Cue> $cues
     *
     * @return list<string>
     */
    private function texts(array $cues): array
    {
        return array_map(static fn (Cue $cue): string => $cue->text, $cues);
    }

    /**
     * @param list<Cue> $cues
     *
     * @return list<array{int, int}>
     */
    private function times(array $cues): array
    {
        return array_map(static fn (Cue $cue): array => [$cue->start, $cue->end], $cues);
    }
}
