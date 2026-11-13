<?php

declare(strict_types=1);

namespace Phil\Stockroom\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Stockroom\Container;
use Phil\Stockroom\Deployment;
use Phil\Stockroom\Exception\InsufficientStock;
use Phil\Stockroom\Exception\InvalidStock;
use Phil\Stockroom\Exception\UnknownStock;
use Phil\Stockroom\Holding;
use Phil\Stockroom\Identifier;
use Phil\Stockroom\Item;
use Phil\Stockroom\ItemCondition;
use Phil\Stockroom\LoadLine;
use Phil\Stockroom\LoadList;
use Phil\Stockroom\Movement;
use Phil\Stockroom\MovementKind;
use Phil\Stockroom\PickLine;
use Phil\Stockroom\Place;
use Phil\Stockroom\PlaceKind;
use Phil\Stockroom\Quantity;
use Phil\Stockroom\ReturnCheck;
use Phil\Stockroom\Shortfall;
use Phil\Stockroom\Text;
use Phil\Stockroom\Tracking;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Identifier::class)]
#[CoversClass(Text::class)]
#[CoversClass(Quantity::class)]
#[CoversClass(Place::class)]
#[CoversClass(Container::class)]
#[CoversClass(Item::class)]
#[CoversClass(ItemCondition::class)]
#[CoversClass(Movement::class)]
#[CoversClass(LoadLine::class)]
#[CoversClass(LoadList::class)]
#[CoversClass(Shortfall::class)]
#[CoversClass(PickLine::class)]
#[CoversClass(Deployment::class)]
#[CoversClass(ReturnCheck::class)]
#[CoversClass(InvalidStock::class)]
#[CoversClass(UnknownStock::class)]
#[CoversClass(InsufficientStock::class)]
final class ValuesTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function notIdentifiers(): iterable
    {
        yield 'empty' => [''];
        yield 'an e-mail address' => ['jo@example.com'];
        yield 'a space' => ['a b'];
        yield 'a leading dot' => ['.hidden'];
        yield 'a leading hyphen' => ['-a'];
        yield 'a slash' => ['a/b'];
        yield 'too long' => [str_repeat('a', 129)];
        yield 'a newline at the end' => ["abc\n"];
    }

    #[DataProvider('notIdentifiers')]
    public function testAnIdentifierHasItsShape(string $value): void
    {
        $this->expectException(InvalidStock::class);
        $this->expectExceptionMessageIsOrContains('The member is 1 to 128 letters, digits, dots, underscores, colons or hyphens, never an e-mail address');

        Identifier::check($value, 'member');
    }

    public function testAnIdentifierIsGivenBack(): void
    {
        self::assertSame('A9._:-z', Identifier::check('A9._:-z', 'x'));
        self::assertSame(str_repeat('a', 128), Identifier::check(str_repeat('a', 128), 'x'));
        self::assertNull(Identifier::optional(null, 'x'));
        self::assertSame('a', Identifier::optional('a', 'x'));
        self::assertSame('9', Identifier::check('9', 'x'));
    }

    public function testTheMessageCutsALongValue(): void
    {
        try {
            Identifier::check(str_repeat('é', 60), 'x');
            self::fail('A long value passed.');
        } catch (InvalidStock $invalidStock) {
            self::assertStringEndsWith('"'.str_repeat('é', 39).'…" is not.', $invalidStock->getMessage());
        }
    }

    public function testATextIsTrimmedAndBounded(): void
    {
        self::assertSame('Crate', Text::check('  Crate ', 'name'));
        self::assertSame(str_repeat('é', 200), Text::check(str_repeat('é', 200), 'name'));
        self::assertSame(str_repeat('é', 1000), Text::optional(str_repeat('é', 1000), 'note'));
        self::assertNull(Text::optional(null, 'note'));
        self::assertSame('Prix 5 € … Étagère', Text::check('Prix 5 € … Étagère', 'name'), 'the bytes of a multibyte letter are no control characters');

        foreach (['', '   ', "a\tb", "a\x00b", "\xff", str_repeat('é', 201)] as $bad) {
            try {
                Text::check($bad, 'name');
                self::fail(\sprintf('"%s" passed.', $bad));
            } catch (InvalidStock $invalid) {
                self::assertSame('The name is a text of 1 to 200 characters, without control characters.', $invalid->getMessage());
            }
        }

        $this->expectException(InvalidStock::class);
        Text::optional(str_repeat('é', 1001), 'note');
    }

    public function testAQuantityIsBounded(): void
    {
        self::assertSame(1, Quantity::check(1));
        self::assertSame(1_000_000, Quantity::check(1_000_000));

        foreach ([0, -1, 1_000_001] as $bad) {
            try {
                Quantity::check($bad);
                self::fail(\sprintf('%d passed.', $bad));
            } catch (InvalidStock $invalid) {
                self::assertSame(\sprintf('A quantity is from 1 to 1000000, not %d.', $bad), $invalid->getMessage());
            }
        }
    }

    public function testAPlaceHasAKeyThatReadsBack(): void
    {
        $shelf = Place::container('shelf-b');
        $fair = Place::event('fair-2026');
        $jo = Place::holder('member-7');

        self::assertSame([PlaceKind::Container, 'shelf-b', 'container/shelf-b', true], [$shelf->kind, $shelf->reference, $shelf->key(), $shelf->isContainer()]);
        self::assertSame(['event/fair-2026', false], [$fair->key(), $fair->isContainer()]);
        self::assertSame(['holder/member-7', false], [$jo->key(), $jo->isContainer()]);
        self::assertTrue(Place::fromKey('holder/member-7')->equals($jo));
        self::assertFalse(Place::event('member-7')->equals($jo));
        self::assertFalse(Place::holder('member-8')->equals($jo));

        foreach (['shelf', 'container', 'drawer/x', '/x', 'container/', 'container/a/b'] as $bad) {
            try {
                Place::fromKey($bad);
                self::fail(\sprintf('"%s" was read.', $bad));
            } catch (InvalidStock) {
            }
        }

        $this->expectExceptionMessageIsOrContains('The reference of a place is');
        Place::holder('jo@example.com');
    }

    public function testTheMessageOfAKeyIsCut(): void
    {
        $this->expectExceptionMessageIsOrContains('"'.substr(str_repeat('0123456789', 5), 0, 39).'…" is not the key of a place.');
        Place::fromKey(str_repeat('0123456789', 5));
    }

    public function testAContainerDoesNotSitInItself(): void
    {
        $crate = new Container('crate', ' Crate 3 ', 'shelf', 'C-3');

        self::assertSame(['crate', 'Crate 3', 'shelf', 'C-3'], [$crate->id, $crate->name, $crate->parent, $crate->code]);
        self::assertSame('container/crate', $crate->place()->key());
        self::assertNull($crate->in(null)->parent);
        self::assertSame(['crate', 'Crate 3', 'room', 'C-3'], [$crate->in('room')->id, $crate->in('room')->name, $crate->in('room')->parent, $crate->in('room')->code]);

        $this->expectExceptionMessageIsOrContains('A container does not sit in itself.');
        $crate->in('crate');
    }

    public function testAContainerChecksItsValues(): void
    {
        foreach ([['', 'n', null, null], ['a', '', null, null], ['a', 'n', 'x y', null], ['a', 'n', null, 'x@y.z']] as [$id, $name, $parent, $code]) {
            try {
                new Container($id, $name, $parent, $code);
                self::fail('A bad container was made.');
            } catch (InvalidStock) {
            }
        }

        self::assertNull(new Container('a', 'n')->code);
    }

    public function testAnItemKeepsItsValues(): void
    {
        $sword = new Item('sword', 'FS-1', ' Foam sword ', Tracking::Unit, 'crate');

        self::assertSame(['sword', 'FS-1', 'Foam sword', Tracking::Unit, 'crate', ItemCondition::Usable, true], [$sword->id, $sword->code, $sword->name, $sword->tracking, $sword->home, $sword->condition, $sword->isUnit()]);
        $repaired = $sword->withCondition(ItemCondition::NeedsRepair);
        self::assertSame([ItemCondition::NeedsRepair, 'crate', Tracking::Unit, 'FS-1', 'Foam sword', 'sword'], [$repaired->condition, $repaired->home, $repaired->tracking, $repaired->code, $repaired->name, $repaired->id]);
        $moved = $repaired->withHome(null);
        self::assertSame([null, ItemCondition::NeedsRepair, Tracking::Unit, 'FS-1'], [$moved->home, $moved->condition, $moved->tracking, $moved->code]);
        self::assertFalse(new Item('a', 'b', 'c')->isUnit());
        self::assertSame([Tracking::Quantity, null, ItemCondition::Usable], [new Item('a', 'b', 'c')->tracking, new Item('a', 'b', 'c')->home, new Item('a', 'b', 'c')->condition]);

        foreach ([['', 'b', 'c', null], ['a', 'b c', 'c', null], ['a', 'b', '', null], ['a', 'b', 'c', 'h@x.y']] as [$id, $code, $name, $home]) {
            try {
                new Item($id, $code, $name, home: $home);
                self::fail('A bad item was made.');
            } catch (InvalidStock) {
            }
        }
    }

    public function testOnlyAUsableItemLeaves(): void
    {
        self::assertTrue(ItemCondition::Usable->mayLeave());
        self::assertFalse(ItemCondition::NeedsRepair->mayLeave());
        self::assertFalse(ItemCondition::Retired->mayLeave());
    }

    public function testAMovementKeepsItsMomentInUtc(): void
    {
        $movement = new Movement('m', MovementKind::Out, 'sword', 2, Place::container('crate'), Place::event('fair'), new DateTimeImmutable('2026-11-13 09:00', new DateTimeZone('America/Toronto')), 'fair', ' Loaded ');

        self::assertSame('2026-11-13T14:00:00+00:00', $movement->at->format(\DATE_ATOM));
        self::assertSame(['m', MovementKind::Out, 'sword', 2, 'container/crate', 'event/fair', 'fair', 'Loaded'], [$movement->id, $movement->kind, $movement->item, $movement->quantity, $movement->from?->key(), $movement->to?->key(), $movement->reference, $movement->note]);
    }

    /**
     * @return iterable<string, array{MovementKind, ?Place, ?Place, bool}>
     */
    public static function places(): iterable
    {
        $crate = Place::container('crate');
        $shelf = Place::container('shelf');
        $fair = Place::event('fair');
        $jo = Place::holder('jo');

        yield 'in to a container' => [MovementKind::In, null, $crate, true];
        yield 'in from a container' => [MovementKind::In, $shelf, $crate, false];
        yield 'in to an event' => [MovementKind::In, null, $fair, false];
        yield 'in to nowhere' => [MovementKind::In, null, null, false];
        yield 'out to an event' => [MovementKind::Out, $crate, $fair, true];
        yield 'out to a holder' => [MovementKind::Out, $crate, $jo, true];
        yield 'out to a container' => [MovementKind::Out, $crate, $shelf, false];
        yield 'out from an event' => [MovementKind::Out, $fair, $jo, false];
        yield 'out to nowhere' => [MovementKind::Out, $crate, null, false];
        yield 'out from nowhere' => [MovementKind::Out, null, $jo, false];
        yield 'a transfer' => [MovementKind::Transfer, $crate, $shelf, true];
        yield 'a transfer in place' => [MovementKind::Transfer, $crate, Place::container('crate'), false];
        yield 'a transfer to an event' => [MovementKind::Transfer, $crate, $fair, false];
        yield 'a transfer from an event' => [MovementKind::Transfer, $fair, $crate, false];
        yield 'a transfer from nowhere' => [MovementKind::Transfer, null, $crate, false];
        yield 'a transfer to nowhere' => [MovementKind::Transfer, $crate, null, false];
        yield 'a loss from a holder' => [MovementKind::Loss, $jo, null, true];
        yield 'a loss from a container' => [MovementKind::Loss, $crate, null, true];
        yield 'a loss to somewhere' => [MovementKind::Loss, $crate, $shelf, false];
        yield 'a loss from nowhere' => [MovementKind::Loss, null, null, false];
        yield 'a return from an event' => [MovementKind::Return, $fair, $crate, true];
        yield 'a return from a container' => [MovementKind::Return, $shelf, $crate, false];
        yield 'a return to a holder' => [MovementKind::Return, $fair, $jo, false];
        yield 'a return from nowhere' => [MovementKind::Return, null, $crate, false];
        yield 'a return to nowhere' => [MovementKind::Return, $fair, null, false];
    }

    #[DataProvider('places')]
    public function testAMovementGoesBetweenThePlacesOfItsKind(MovementKind $kind, ?Place $from, ?Place $to, bool $fits): void
    {
        if (!$fits) {
            $this->expectException(InvalidStock::class);
            $this->expectExceptionMessageIsOrContains(\sprintf('A movement "%s" does not go from %s to %s.', $kind->value, $from?->key() ?? 'nowhere', $to?->key() ?? 'nowhere'));
        }

        self::assertSame($kind, new Movement('m', $kind, 'i', 1, $from, $to, new DateTimeImmutable())->kind);
    }

    public function testAMovementChecksItsValues(): void
    {
        foreach ([['', 'i', 1, null, null], ['m', '', 1, null, null], ['m', 'i', 0, null, null], ['m', 'i', 1, 'a b', null], ['m', 'i', 1, null, '']] as [$id, $item, $quantity, $reference, $note]) {
            try {
                new Movement($id, MovementKind::In, $item, $quantity, null, Place::container('c'), new DateTimeImmutable(), $reference, $note);
                self::fail('A bad movement was made.');
            } catch (InvalidStock) {
            }
        }

        self::assertNull(new Movement('m', MovementKind::In, 'i', 1, null, Place::container('c'), new DateTimeImmutable())->note);
    }

    public function testALoadListMergesItsLines(): void
    {
        $list = new LoadList('fair', [new LoadLine('sword', 2), new LoadLine('lantern', 1, 'crate'), new LoadLine('sword', 3), new LoadLine('sword', 1, 'crate')]);

        self::assertSame('fair', $list->event);
        self::assertEquals([new LoadLine('sword', 5), new LoadLine('lantern', 1, 'crate'), new LoadLine('sword', 1, 'crate')], $list->lines);
        self::assertSame(['sword' => 6, 'lantern' => 1], $list->totals());
        self::assertSame([1, null], [new LoadLine('a')->quantity, new LoadLine('a')->from]);
    }

    public function testALoadListKeepsApartTheLinesWhoseNamesRunTogether(): void
    {
        $list = new LoadList('fair', [new LoadLine('ab', 1, 'c'), new LoadLine('a', 2, 'bc'), new LoadLine('a', 3)]);

        self::assertEquals([new LoadLine('ab', 1, 'c'), new LoadLine('a', 2, 'bc'), new LoadLine('a', 3)], $list->lines);
    }

    public function testALoadListNamesTheLinesThatNameAContainer(): void
    {
        $named = new LoadLine('sword', 1, 'crate');
        $list = new LoadList('fair', [new LoadLine('sword', 2), $named, new LoadLine('lantern')]);

        self::assertEquals([$named], $list->named('sword'));
        self::assertSame([], $list->named('lantern'));
        self::assertSame([], $list->named('ghost'));
    }

    public function testALoadListIsBounded(): void
    {
        $lines = array_map(static fn (int $n): LoadLine => new LoadLine('item-'.$n), range(1, 500));
        self::assertCount(500, new LoadList('fair', [...$lines, new LoadLine('item-1')])->lines);

        $this->expectExceptionMessageIsOrContains('A load list holds at most 500 lines.');
        new LoadList('fair', [...$lines, new LoadLine('item-501')]);
    }

    public function testALoadListChecksItsEvent(): void
    {
        $this->expectException(InvalidStock::class);
        new LoadList('a b', []);
    }

    public function testALoadLineChecksItsValues(): void
    {
        foreach ([['a b', 1, null], ['a', 0, null], ['a', 1, 'c d']] as [$item, $quantity, $from]) {
            try {
                new LoadLine($item, $quantity, $from);
                self::fail('A bad line was made.');
            } catch (InvalidStock) {
            }
        }

        self::assertSame('c', new LoadLine('a', 1, 'c')->from);
    }

    public function testTheReportsSayWhetherTheyAreComplete(): void
    {
        $gap = new Shortfall('sword', 5, 2);
        self::assertSame(3, $gap->missing());
        self::assertSame(['sword', 5, 2], [$gap->item, $gap->expected, $gap->found]);
        self::assertTrue(new Deployment([], [])->isComplete());
        self::assertFalse(new Deployment([], [$gap])->isComplete());
        self::assertTrue(new ReturnCheck([], [], ['x' => 1])->isComplete());
        self::assertFalse(new ReturnCheck([], [$gap], [])->isComplete());

        $holding = new Holding('sword', Place::container('crate'), 4);
        self::assertTrue(new PickLine(new LoadLine('sword', 5), [$holding], 4)->isShort());
        self::assertFalse(new PickLine(new LoadLine('sword', 4), [$holding], 4)->isShort());
    }

    public function testTheMessagesOfTheUnknown(): void
    {
        self::assertSame('There is no container "c".', UnknownStock::container('c')->getMessage());
        self::assertSame('There is no item "i".', UnknownStock::item('i')->getMessage());
        self::assertSame('The event "e" has no load list.', UnknownStock::loadList('e')->getMessage());
        self::assertSame('short', InsufficientStock::because('short')->getMessage());
    }
}
