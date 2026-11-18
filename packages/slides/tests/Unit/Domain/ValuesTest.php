<?php

declare(strict_types=1);

namespace Phil\Slides\Tests\Unit\Domain;

use Phil\Slides\Domain\AspectRatio;
use Phil\Slides\Domain\Exception\DeckRefused;
use Phil\Slides\Domain\Exception\InvalidOption;
use Phil\Slides\Domain\Exception\SlidesException;
use Phil\Slides\Domain\NotesPageSize;
use Phil\Slides\Domain\SplitMode;
use Phil\Slides\Domain\ValueObject\Deck;
use Phil\Slides\Domain\ValueObject\DeckSettings;
use Phil\Slides\Domain\ValueObject\Slide;
use Phil\Ujo\Block;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AspectRatio::class)]
#[CoversClass(SplitMode::class)]
#[CoversClass(NotesPageSize::class)]
#[CoversClass(Deck::class)]
#[CoversClass(DeckSettings::class)]
#[CoversClass(Slide::class)]
#[CoversClass(DeckRefused::class)]
#[CoversClass(InvalidOption::class)]
final class ValuesTest extends TestCase
{
    public function testAnAspectRatioGivesItsPageAndItsCss(): void
    {
        self::assertSame([960.0, 540.0], [AspectRatio::Wide->pageSize()->width, AspectRatio::Wide->pageSize()->height]);
        self::assertSame([720.0, 540.0], [AspectRatio::Standard->pageSize()->width, AspectRatio::Standard->pageSize()->height]);
        self::assertSame('16 / 9', AspectRatio::Wide->css());
        self::assertSame('4 / 3', AspectRatio::Standard->css());
    }

    public function testASplitModeSaysWhereItCuts(): void
    {
        self::assertSame(SplitMode::Separators, SplitMode::Auto->resolve(true));
        self::assertSame(SplitMode::Headings, SplitMode::Auto->resolve(false));
        self::assertSame(SplitMode::Both, SplitMode::Both->resolve(false));
        self::assertSame(SplitMode::Headings, SplitMode::Headings->resolve(true));

        self::assertSame([true, true, false, true], array_map(static fn (SplitMode $mode): bool => $mode->cutsAtSeparators(), SplitMode::cases()));
        self::assertSame([false, false, true, true], array_map(static fn (SplitMode $mode): bool => $mode->cutsAtHeadings(), SplitMode::cases()));
    }

    public function testANotesPageIsUpright(): void
    {
        self::assertSame([612.0, 792.0], [NotesPageSize::Letter->pageSize()->width, NotesPageSize::Letter->pageSize()->height]);
        self::assertSame([595.0, 842.0], [NotesPageSize::A4->pageSize()->width, NotesPageSize::A4->pageSize()->height]);
    }

    public function testASlideKnowsItsNotesAndItsAnchor(): void
    {
        $block = new Block('b1', 'paragraph', ['text' => 'Hi']);

        self::assertFalse(new Slide(1, [$block])->hasNotes());
        self::assertTrue(new Slide(1, [], [$block])->hasNotes());
        self::assertTrue(new Slide(1, [], [], ['Say it'])->hasNotes());
        self::assertSame('slide-12', new Slide(12, [])->anchor());

        $slide = new Slide(2, [$block], [], [], ['lead'], false, 'Title');
        self::assertSame(['lead'], $slide->classes);
        self::assertFalse($slide->paginated);
        self::assertSame('Title', $slide->title);
        self::assertTrue(new Slide(1, [])->paginated);
        self::assertNull(new Slide(1, [])->title);
    }

    public function testADeckFindsItsSlidesAndItsNotes(): void
    {
        $plain = new Slide(1, []);
        $noted = new Slide(2, [], [], ['Note']);
        $deck = new Deck('talk', [$plain, $noted], new DeckSettings('Talk'), ['theme']);

        self::assertSame(2, $deck->count());
        self::assertSame($plain, $deck->slide(1));
        self::assertSame($noted, $deck->slide(2));
        self::assertNull($deck->slide(3));
        self::assertNull($deck->slide(0));
        self::assertTrue($deck->hasNotes());
        self::assertFalse(new Deck('talk', [$plain])->hasNotes());
        self::assertNull(new Deck('talk', [])->settings->title);

        $renamed = $deck->withId('talk:42.v-2_b');
        self::assertSame('talk:42.v-2_b', $renamed->id);
        self::assertSame($deck->slides, $renamed->slides);
        self::assertSame($deck->settings, $renamed->settings);
        self::assertSame(['theme'], $renamed->ignoredDirectives);
        self::assertSame(str_repeat('a', 128), new Deck(str_repeat('a', 128), [])->id);
    }

    public function testADeckIdentifierIsChecked(): void
    {
        foreach (['', 'two words', str_repeat('a', 129), "line\n", 'a/b'] as $id) {
            try {
                new Deck($id, []);
                self::fail('The identifier should be refused.');
            } catch (InvalidOption $exception) {
                self::assertInstanceOf(SlidesException::class, $exception);
            }
        }

        $this->expectExceptionMessageIsOrContains('The identifier of a deck is 1 to 128 letters, digits, "_", "-", "." or ":"; "a b" is not.');
        new Deck('talk', [])->withId('a b');
    }

    public function testTheSettingsHaveDefaults(): void
    {
        $settings = new DeckSettings();

        self::assertSame([null, null, [], false, null, null, AspectRatio::Wide], [$settings->title, $settings->language, $settings->classes, $settings->paginate, $settings->header, $settings->footer, $settings->aspectRatio]);
    }

    public function testTheRefusalsSayWhy(): void
    {
        self::assertSame('The source is 12 bytes long, over the 10 bytes a deck is read from.', DeckRefused::sourceTooLarge(12, 10)->getMessage());
        self::assertSame('The document nests its blocks and their data more than 3 levels deep.', DeckRefused::tooDeep(3)->getMessage());
        self::assertSame('The document makes more than 7 slides, the most a deck holds.', DeckRefused::tooManySlides(7)->getMessage());
        self::assertInstanceOf(SlidesException::class, DeckRefused::tooDeep(3));
        self::assertSame('Why', InvalidOption::because('Why')->getMessage());
    }
}
