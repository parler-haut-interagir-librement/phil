<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\EditorialCalendar\AuthorId;
use Phil\EditorialCalendar\Exception\InvalidItem;
use Phil\EditorialCalendar\Exception\InvalidTransition;
use Phil\EditorialCalendar\ItemState;
use Phil\EditorialCalendar\Moment;
use Phil\EditorialCalendar\PlannedItem;
use Phil\EditorialCalendar\PublicationBatch;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PlannedItem::class)]
#[CoversClass(ItemState::class)]
#[CoversClass(AuthorId::class)]
#[CoversClass(PublicationBatch::class)]
#[CoversClass(Moment::class)]
#[CoversClass(InvalidItem::class)]
#[CoversClass(InvalidTransition::class)]
final class PlannedItemTest extends TestCase
{
    public function testAnItemIsPlannedAsAnIdeaWithItsMomentInUtc(): void
    {
        $item = PlannedItem::plan('a-1', ResourceReference::of('article', '42'), 'Title', 'fr-CA', new AuthorId('user:7'), new DateTimeImmutable('2026-11-16 09:00', new DateTimeZone('America/Toronto')));

        self::assertSame('a-1', $item->id);
        self::assertSame('article:42', $item->content->toString());
        self::assertSame('Title', $item->title);
        self::assertSame('fr-CA', $item->locale);
        self::assertSame('user:7', $item->author->value);
        self::assertSame(ItemState::Idea, $item->state);
        self::assertSame('2026-11-16T14:00:00+00:00', $item->publishAt?->format('c'));
        self::assertSame('UTC', $item->publishAt->getTimezone()->getName());
        self::assertNull($this->item()->publishAt);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function refused(): iterable
    {
        yield 'an empty identifier' => ['', 'T', 'fr'];
        yield 'an identifier with a space' => ['a b', 'T', 'fr'];
        yield 'an identifier with a slash' => ['a/b', 'T', 'fr'];
        yield 'a too long identifier' => [str_repeat('a', 129), 'T', 'fr'];
        yield 'a blank title' => ['a', ' ', 'fr'];
        yield 'a too long title' => ['a', str_repeat('é', 501), 'fr'];
        yield 'an empty language' => ['a', 'T', ''];
        yield 'a language in capitals' => ['a', 'T', 'FR'];
        yield 'a language of one letter' => ['a', 'T', 'f'];
        yield 'a language of four letters' => ['a', 'T', 'fren'];
        yield 'a language with an underscore' => ['a', 'T', 'fr_CA'];
        yield 'a language with a short subtag' => ['a', 'T', 'fr-C'];
        yield 'a language with a long subtag' => ['a', 'T', 'fr-ABCDEFGHI'];
        yield 'a language with four subtags' => ['a', 'T', 'zh-Hant-TW-ab-cd'];
        yield 'a language with a line break' => ['a', 'T', "fr\n"];
        yield 'an identifier with a line break' => ["a\n", 'T', 'fr'];
    }

    #[DataProvider('refused')]
    public function testItsValuesAreChecked(string $id, string $title, string $locale): void
    {
        $this->expectException(InvalidItem::class);
        PlannedItem::plan($id, ResourceReference::of('article', '1'), $title, $locale, new AuthorId('u'));
    }

    public function testTheLongestValuesAreAccepted(): void
    {
        $item = PlannedItem::plan(str_repeat('a', 128), ResourceReference::of('article', '1'), str_repeat('é', 500), 'zh-Hant-TW-abcdefgh', new AuthorId(str_repeat('u', 128)));

        self::assertSame(128, \strlen($item->id));
        self::assertSame('ast', PlannedItem::plan('A.b_c:d-1', ResourceReference::of('a', '1'), 'T', 'ast', new AuthorId('u'))->locale);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedAuthors(): iterable
    {
        yield 'an address' => ['someone@example.org'];
        yield 'empty' => [''];
        yield 'a space' => ['a b'];
        yield 'too long' => [str_repeat('a', 129)];
        yield 'a line break' => ["a\n"];
    }

    #[DataProvider('refusedAuthors')]
    public function testAnAuthorIsAStableIdentifierNeverAnAddress(string $value): void
    {
        $this->expectException(InvalidItem::class);
        $this->expectExceptionMessageIsOrContains('never by an address');
        new AuthorId($value);
    }

    public function testAuthorsAreComparedByValue(): void
    {
        self::assertTrue(new AuthorId('a')->equals(new AuthorId('a')));
        self::assertFalse(new AuthorId('a')->equals(new AuthorId('b')));
    }

    /**
     * @return iterable<string, array{ItemState, list<ItemState>}>
     */
    public static function workflow(): iterable
    {
        yield 'idea' => [ItemState::Idea, [ItemState::Drafting, ItemState::Withdrawn]];
        yield 'drafting' => [ItemState::Drafting, [ItemState::Idea, ItemState::Ready, ItemState::Withdrawn]];
        yield 'ready' => [ItemState::Ready, [ItemState::Drafting, ItemState::Scheduled, ItemState::Published, ItemState::Withdrawn]];
        yield 'scheduled' => [ItemState::Scheduled, [ItemState::Ready, ItemState::Published, ItemState::Withdrawn]];
        yield 'published' => [ItemState::Published, [ItemState::Withdrawn]];
        yield 'withdrawn' => [ItemState::Withdrawn, [ItemState::Idea]];
    }

    /**
     * @param list<ItemState> $allowed
     */
    #[DataProvider('workflow')]
    public function testTheWorkflowAllowsOnlyItsSteps(ItemState $from, array $allowed): void
    {
        foreach (ItemState::cases() as $to) {
            self::assertSame(\in_array($to, $allowed, true), $from->canBecome($to), $from->value.' to '.$to->value);
        }
    }

    public function testOnlyPublishedAndWithdrawnItemsAreNoLongerPending(): void
    {
        self::assertSame(
            ['idea' => true, 'drafting' => true, 'ready' => true, 'scheduled' => true, 'published' => false, 'withdrawn' => false],
            array_combine(array_map(static fn (ItemState $state): string => $state->value, ItemState::cases()), array_map(static fn (ItemState $state): bool => $state->isPending(), ItemState::cases())),
        );
    }

    public function testATransitionGivesANewItem(): void
    {
        $idea = $this->item();
        $draft = $idea->transitionTo(ItemState::Drafting);

        self::assertSame(ItemState::Idea, $idea->state);
        self::assertSame(ItemState::Drafting, $draft->state);
        self::assertSame([$idea->id, $idea->content, $idea->title, $idea->locale, $idea->author], [$draft->id, $draft->content, $draft->title, $draft->locale, $draft->author]);
    }

    public function testATransitionTheWorkflowRefusesThrows(): void
    {
        $this->expectException(InvalidTransition::class);
        $this->expectExceptionMessageIsOrContains('The item "a" cannot go from "idea" to "published".');
        $this->item()->transitionTo(ItemState::Published);
    }

    public function testAScheduledItemNeedsAMoment(): void
    {
        $ready = $this->item()->transitionTo(ItemState::Drafting)->transitionTo(ItemState::Ready);

        try {
            $ready->transitionTo(ItemState::Scheduled);
            self::fail('A scheduled item without a moment was accepted.');
        } catch (InvalidTransition $invalidTransition) {
            self::assertSame('The item "a" is scheduled only with a moment to publish it at.', $invalidTransition->getMessage());
        }

        $scheduled = $ready->transitionTo(ItemState::Scheduled, new DateTimeImmutable('2026-12-01 10:00+01:00'));
        self::assertSame('2026-12-01T09:00:00+00:00', $scheduled->publishAt?->format('c'));

        $planned = $ready->reschedule(new DateTimeImmutable('2026-12-02 10:00Z'))->transitionTo(ItemState::Scheduled);
        self::assertSame('2026-12-02T10:00:00+00:00', $planned->publishAt?->format('c'));

        $published = $planned->transitionTo(ItemState::Published);
        self::assertSame('2026-12-02T10:00:00+00:00', $published->publishAt?->format('c'));
        self::assertSame('2026-12-03T00:00:00+00:00', $planned->transitionTo(ItemState::Published, new DateTimeImmutable('2026-12-02 19:00-05:00'))->publishAt?->format('c'));
    }

    public function testAPendingItemIsRescheduled(): void
    {
        $item = $this->item()->reschedule(new DateTimeImmutable('2026-12-01 10:00+02:00'));

        self::assertSame('2026-12-01T08:00:00+00:00', $item->publishAt?->format('c'));
        self::assertNull($item->reschedule(null)->publishAt);
        self::assertSame(ItemState::Idea, $item->state);
    }

    public function testAScheduledItemIsNeverLeftWithoutAMoment(): void
    {
        $scheduled = $this->item()->transitionTo(ItemState::Drafting)->transitionTo(ItemState::Ready)->transitionTo(ItemState::Scheduled, new DateTimeImmutable('2026-12-01Z'));

        self::assertSame('2026-12-05T00:00:00+00:00', $scheduled->reschedule(new DateTimeImmutable('2026-12-05Z'))->publishAt?->format('c'));
        $this->expectException(InvalidTransition::class);
        $scheduled->reschedule(null);
    }

    /**
     * @return iterable<string, array{ItemState}>
     */
    public static function finished(): iterable
    {
        yield 'published' => [ItemState::Published];
        yield 'withdrawn' => [ItemState::Withdrawn];
    }

    #[DataProvider('finished')]
    public function testAFinishedItemIsNotRescheduled(ItemState $state): void
    {
        $item = PlannedItem::restore('a', ResourceReference::of('article', '1'), 'T', 'fr', new AuthorId('u'), $state, new DateTimeImmutable('2026-12-01Z'));

        $this->expectException(InvalidTransition::class);
        $item->reschedule(new DateTimeImmutable('2026-12-02Z'));
    }

    public function testAnItemIsRestoredInAnyState(): void
    {
        $item = PlannedItem::restore('a', ResourceReference::of('article', '1'), 'T', 'fr', new AuthorId('u'), ItemState::Scheduled, new DateTimeImmutable('2026-12-01 01:00+01:00'));

        self::assertSame(ItemState::Scheduled, $item->state);
        self::assertSame('2026-12-01T00:00:00+00:00', $item->publishAt?->format('c'));
        self::assertNull(PlannedItem::restore('a', ResourceReference::of('article', '1'), 'T', 'fr', new AuthorId('u'), ItemState::Ready, null)->publishAt);

        $this->expectException(InvalidTransition::class);
        PlannedItem::restore('a', ResourceReference::of('article', '1'), 'T', 'fr', new AuthorId('u'), ItemState::Scheduled, null);
    }

    public function testARestoredItemIsChecked(): void
    {
        $this->expectException(InvalidItem::class);
        PlannedItem::restore('a b', ResourceReference::of('article', '1'), 'T', 'fr', new AuthorId('u'), ItemState::Idea, null);
    }

    public function testAnItemIsEdited(): void
    {
        $item = $this->item();
        $edited = $item->edit('Other', 'en', new AuthorId('v'));

        self::assertSame(['Other', 'en', 'v'], [$edited->title, $edited->locale, $edited->author->value]);
        self::assertEquals($item, $item->edit());

        $this->expectException(InvalidItem::class);
        $item->edit(locale: 'english');
    }

    public function testAnItemHasASection(): void
    {
        $item = PlannedItem::plan('a', ResourceReference::of('article', '1'), 'T', 'fr', new AuthorId('u'), null, 'news');

        self::assertSame('news', $item->section);
        self::assertNull($this->item()->section);
        self::assertSame('news', $item->transitionTo(ItemState::Drafting)->section);
        self::assertSame('news', $item->reschedule(new DateTimeImmutable('2026-12-01Z'))->section);
        self::assertSame('news', $item->edit('Other')->section);
        self::assertSame('blog', $item->edit(section: 'blog')->section);
        self::assertNull($item->withoutSection()->section);
        self::assertSame('Other', $item->edit('Other')->withoutSection()->title);
        self::assertSame(str_repeat('s', 128), $item->edit(section: str_repeat('s', 128))->section);
        self::assertSame('A.b_c:d-1', $item->edit(section: 'A.b_c:d-1')->section);
        self::assertSame('news', PlannedItem::restore('a', ResourceReference::of('article', '1'), 'T', 'fr', new AuthorId('u'), ItemState::Ready, null, 'news')->section);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedSections(): iterable
    {
        yield 'empty' => [''];
        yield 'a space' => ['a b'];
        yield 'too long' => [str_repeat('s', 129)];
        yield 'a slash' => ['news/local'];
        yield 'a new line after it' => ["news\n"];
    }

    #[DataProvider('refusedSections')]
    public function testASectionIsChecked(string $section): void
    {
        $this->expectException(InvalidItem::class);
        $this->expectExceptionMessageIsOrContains('An item section is 1 to 128');
        PlannedItem::plan('a', ResourceReference::of('article', '1'), 'T', 'fr', new AuthorId('u'), null, $section);
    }

    public function testABatchIsChecked(): void
    {
        $batch = new PublicationBatch('b', 'Wave', ['a', 'b'], new DateTimeImmutable('2026-12-01 01:00+01:00'));

        self::assertSame('2026-12-01T00:00:00+00:00', $batch->at?->format('c'));
        self::assertFalse($batch->isManual());
        self::assertTrue(new PublicationBatch('b', 'Wave', ['a'])->isManual());
        self::assertTrue($batch->holds('a'));
        self::assertFalse($batch->holds('c'));
        self::assertSame(['a', 'b'], $batch->items);
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function refusedBatches(): iterable
    {
        yield 'an identifier with a space' => ['a b', 'T', ['a']];
        yield 'an empty identifier' => ['', 'T', ['a']];
        yield 'a too long identifier' => [str_repeat('b', 129), 'T', ['a']];
        yield 'a blank title' => ['b', "\t", ['a']];
        yield 'a too long title' => ['b', str_repeat('t', 501), ['a']];
        yield 'no item' => ['b', 'T', []];
        yield 'a repeated item' => ['b', 'T', ['a', 'c', 'a']];
    }

    /**
     * @param list<string> $items
     */
    #[DataProvider('refusedBatches')]
    public function testABatchRefusesWrongValues(string $id, string $title, array $items): void
    {
        $this->expectException(InvalidItem::class);
        new PublicationBatch($id, $title, $items);
    }

    public function testTheLongestBatchValuesAreAccepted(): void
    {
        self::assertSame(128, \strlen(new PublicationBatch(str_repeat('b', 128), str_repeat('é', 500), ['a'])->id));
    }

    public function testMomentsAreComparedByTheirOrder(): void
    {
        $paris = new DateTimeImmutable('2026-12-01 01:00+01:00');
        $utc = new DateTimeImmutable('2026-12-01 00:00Z');

        self::assertTrue(Moment::same($paris, $utc));
        self::assertFalse(Moment::same($paris, $utc->modify('+1 second')));
        self::assertTrue(Moment::within($utc, $paris, $utc->modify('+1 second')));
        self::assertFalse(Moment::within($utc->modify('+1 second'), $paris, $utc->modify('+1 second')));
        self::assertFalse(Moment::within($utc->modify('-1 second'), $paris, $utc->modify('+1 second')));
    }

    private function item(): PlannedItem
    {
        return PlannedItem::plan('a', ResourceReference::of('article', '1'), 'Title', 'fr', new AuthorId('u'));
    }
}
