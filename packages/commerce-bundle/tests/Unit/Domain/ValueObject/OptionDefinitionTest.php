<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\ValueObject;

use Closure;
use Phil\CommerceBundle\Domain\Exception\InvalidConfiguration;
use Phil\CommerceBundle\Domain\Option\OptionKind;
use Phil\CommerceBundle\Domain\Option\TextSource;
use Phil\CommerceBundle\Domain\ValueObject\AmountRange;
use Phil\CommerceBundle\Domain\ValueObject\LineConfiguration;
use Phil\CommerceBundle\Domain\ValueObject\OptionChoice;
use Phil\CommerceBundle\Domain\ValueObject\OptionDefinition;
use Phil\CommerceBundle\Domain\ValueObject\OptionSet;
use Phil\CommerceBundle\Domain\ValueObject\OptionValue;
use Phil\CommerceBundle\Domain\ValueObject\PrintZone;
use Phil\CommerceBundle\Domain\ValueObject\TextConstraints;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(OptionDefinition::class)]
#[CoversClass(AmountRange::class)]
#[CoversClass(OptionChoice::class)]
#[CoversClass(OptionSet::class)]
#[CoversClass(PrintZone::class)]
#[CoversClass(TextConstraints::class)]
#[CoversClass(InvalidConfiguration::class)]
final class OptionDefinitionTest extends TestCase
{
    public function testATextOptionCarriesItsConstraints(): void
    {
        $zone = PrintZone::of('chest', 0, 0, 1, 1);
        $text = TextConstraints::of(20, 2, '\p{L} ', 'Serif Bold', $zone);
        $option = OptionDefinition::text('name', 'Name', $text, true, $this->cad(500));

        self::assertSame('name', $option->code);
        self::assertSame('Name', $option->label);
        self::assertSame(OptionKind::Text, $option->kind);
        self::assertTrue($option->required);
        self::assertSame(500, $option->supplement?->minorUnits);
        self::assertSame($text, $option->text);
        self::assertSame([], $option->choices);
        self::assertNull($option->drawer);
        self::assertSame(2, $text->minLength);
        self::assertSame(20, $text->maxLength);
        self::assertSame('\p{L} ', $text->characters);
        self::assertSame('Serif Bold', $text->font);
        self::assertSame($zone, $text->zone);
        self::assertSame([TextSource::Free], $text->sources);
        self::assertSame([], $text->entries);
        self::assertSame(['chest', 0, 0, 1, 1], [$zone->name, $zone->x, $zone->y, $zone->width, $zone->height]);
        self::assertFalse(OptionDefinition::text('name', 'Name', $text)->required);
    }

    public function testAChoiceOptionKeysItsChoices(): void
    {
        $plain = OptionChoice::of('plain', 'Plain');
        $marble = OptionChoice::of('marble-2', 'Marble', 'images/marble.png', $this->cad(300));
        $option = OptionDefinition::choice('stone', 'Stone', [$plain, $marble]);

        self::assertSame(OptionKind::Choice, $option->kind);
        self::assertFalse($option->required);
        self::assertNull($option->supplement);
        self::assertSame(['plain' => $plain, 'marble-2' => $marble], $option->choices);
        self::assertSame(['marble-2', 'Marble', 'images/marble.png', 300], [$marble->code, $marble->label, $marble->image, $marble->supplement?->minorUnits]);
        self::assertNull($plain->image);
        self::assertNull($plain->supplement);
        self::assertTrue(OptionDefinition::choice('stone', 'Stone', [$plain], true, $this->cad(1))->required);
        self::assertSame(OptionKind::Ink, OptionDefinition::ink('ink', 'Ink', [$plain])->kind);
        self::assertFalse(OptionDefinition::ink('ink', 'Ink', [$plain])->required);
        self::assertTrue(OptionDefinition::ink('ink', 'Ink', [$plain], true)->required);
        self::assertSame(2, OptionDefinition::ink('ink', 'Ink', [$plain], supplement: $this->cad(2))->supplement?->minorUnits);
    }

    public function testADrawOptionIsAlwaysRequiredAndNamesItsDrawer(): void
    {
        $option = OptionDefinition::draw('loot', 'Loot', 'tables:3', $this->cad(700));

        self::assertSame(OptionKind::Draw, $option->kind);
        self::assertTrue($option->required);
        self::assertSame('tables:3', $option->drawer);
        self::assertSame(700, $option->supplement?->minorUnits);
        self::assertNull(OptionDefinition::draw('loot', 'Loot', 'tables:3')->supplement);
    }

    public function testATextIsCheckedByLengthAndCharacters(): void
    {
        $option = OptionDefinition::text('name', 'Name', TextConstraints::of(5, 2, '\p{L}'));

        $value = $option->check(OptionValue::chosen('Éloï'));
        self::assertNotNull($value);
        self::assertSame('Éloï', $value->value);
        self::assertSame('Name', $value->label);
        self::assertNull($value->display);
        self::assertNull($value->drawer);
        self::assertSame('Éloïs', $option->check(OptionValue::chosen('Éloïs'))?->value);
        self::assertSame('Al', $option->check(OptionValue::chosen('Al'))?->value);
        self::assertNull($option->check(null));

        foreach (['A' => 'it holds 1 characters, not 2 to 5', 'Abcdef' => 'it holds 6 characters, not 2 to 5', 'Al1' => 'it holds characters the option does not allow'] as $text => $reason) {
            try {
                $option->check(OptionValue::chosen($text));
                self::fail(\sprintf('"%s" was accepted.', $text));
            } catch (InvalidConfiguration $invalidConfiguration) {
                self::assertSame('name', $invalidConfiguration->option);
                self::assertSame(\sprintf('The value of the option name is refused: %s.', $reason), $invalidConfiguration->getMessage());
            }
        }

        self::assertSame('A1 !', OptionDefinition::text('name', 'Name', TextConstraints::of(5))->check(OptionValue::chosen('A1 !'))?->value);
    }

    public function testATextMayBePickedFromItsEntriesOrDrawnAmongThem(): void
    {
        $listed = TextConstraints::of(3, 1, '\d', sources: [TextSource::List], entries: ['A long entry', 'Another']);
        $option = OptionDefinition::text('verse', 'Verse', $listed);

        self::assertSame('A long entry', $option->check(OptionValue::chosen('A long entry'))?->value);
        $this->assertRefused($option, OptionValue::chosen('12'), 'it is not one of the entries');
        $this->assertRefused($option, OptionValue::toDraw(), 'it is not drawn');

        $all = OptionDefinition::text('verse', 'Verse', TextConstraints::of(3, sources: [TextSource::Free, TextSource::List, TextSource::Drawn], entries: ['Another']), drawer: 'verses');
        self::assertSame('Another', $all->check(OptionValue::chosen('Another'))?->value);
        self::assertSame('Own', $all->check(OptionValue::chosen('Own'))?->value);
        $drawn = $all->check(OptionValue::toDraw());
        self::assertNotNull($drawn);
        self::assertTrue($drawn->isPending());
        self::assertSame('verses', $drawn->drawer);
        self::assertSame('Verse', $drawn->label);

        $typed = TextConstraints::of(3, sources: [TextSource::Free, TextSource::Drawn], entries: ['Another']);
        $this->assertRefused(OptionDefinition::text('verse', 'Verse', $typed, drawer: 'verses'), OptionValue::chosen('Another'), 'it holds 7 characters, not 1 to 3');
    }

    public function testAChoiceIsOneOfItsChoicesShownByItsLabel(): void
    {
        $option = OptionDefinition::choice('stone', 'Stone', [OptionChoice::of('plain', 'Plain stone')], true);

        $value = $option->check(OptionValue::chosen('plain'));
        self::assertSame(['plain', 'Stone', 'Plain stone'], [$value?->value, $value?->label, $value?->display]);
        $this->assertRefused($option, OptionValue::chosen('marble'), '"marble" is not one of its choices');
        $this->assertRefused($option, OptionValue::toDraw(), 'it is not drawn');

        try {
            $option->check(null);
            self::fail('A required option was left unset.');
        } catch (InvalidConfiguration $invalidConfiguration) {
            self::assertSame('stone', $invalidConfiguration->option);
            self::assertSame('The option stone is required.', $invalidConfiguration->getMessage());
        }
    }

    public function testADrawIsSetWhetherGivenOrNotAndNeverChosen(): void
    {
        $option = OptionDefinition::draw('loot', 'Loot', 'tables');

        foreach ([null, OptionValue::toDraw()] as $given) {
            $value = $option->check($given);
            self::assertNotNull($value);
            self::assertTrue($value->isPending());
            self::assertSame(['Loot', 'tables'], [$value->label, $value->drawer]);
        }

        $this->assertRefused($option, OptionValue::chosen('123456'), 'it is drawn after payment, not chosen');
    }

    public function testAnAmountIsChosenWithinItsRange(): void
    {
        $range = AmountRange::of($this->cad(500), $this->cad(5_000));
        $option = OptionDefinition::amount('price', 'Your price', $range);

        self::assertSame([OptionKind::Amount, true, null, null, [], null], [$option->kind, $option->required, $option->supplement, $option->text, $option->choices, $option->drawer]);
        self::assertSame($range, $option->range);
        self::assertFalse(OptionDefinition::amount('tip', 'Tip', $range, false)->required);
        self::assertNull(OptionDefinition::text('name', 'Name', TextConstraints::of(5))->range);

        foreach (['500' => '5.00 CAD', '1234' => '12.34 CAD', '5000' => '50.00 CAD'] as $chosen => $display) {
            $value = $option->check(OptionValue::chosen((string) $chosen));
            self::assertSame([(string) $chosen, 'Your price', $display, null], [$value?->value, $value?->label, $value?->display, $value?->drawer]);
        }

        $this->assertRefused($option, OptionValue::chosen('499'), 'the amount is 5.00 or more');
        $this->assertRefused($option, OptionValue::chosen('5001'), 'the amount is 50.00 or less');
        $this->assertRefused($option, OptionValue::chosen('-500'), '"-500" is not an amount in minor units');
        $this->assertRefused($option, OptionValue::toDraw(), 'it is not drawn');

        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIs('The option price is required.');

        $option->check(null);
    }

    public function testTheSupplementOfAnAmountIsTheAmountChosen(): void
    {
        $option = OptionDefinition::amount('price', 'Price', AmountRange::of($this->cad(0), $this->cad(5_000)));

        $charged = $option->supplement(OptionValue::chosen('1234'));
        self::assertInstanceOf(Money::class, $charged);
        self::assertSame([1_234, 'CAD'], [$charged->minorUnits, $charged->currency->code]);
        self::assertNull($option->supplement(OptionValue::chosen('0')));
        self::assertNull($option->supplement(OptionValue::chosen('twelve')));
        self::assertNull($option->supplement(OptionValue::toDraw()));
    }

    public function testTheSupplementIsTheOptionsAndTheChoices(): void
    {
        $choices = [OptionChoice::of('plain', 'Plain'), OptionChoice::of('glow', 'Glow', supplement: $this->cad(400))];

        self::assertNull(OptionDefinition::choice('ink', 'Ink', $choices)->supplement(OptionValue::chosen('plain')));
        self::assertSame(400, OptionDefinition::choice('ink', 'Ink', $choices)->supplement(OptionValue::chosen('glow'))?->minorUnits);
        self::assertSame(100, OptionDefinition::choice('ink', 'Ink', $choices, supplement: $this->cad(100))->supplement(OptionValue::chosen('plain'))?->minorUnits);
        self::assertSame(500, OptionDefinition::choice('ink', 'Ink', $choices, supplement: $this->cad(100))->supplement(OptionValue::chosen('glow'))?->minorUnits);
        self::assertSame(100, OptionDefinition::choice('ink', 'Ink', $choices, supplement: $this->cad(100))->supplement(OptionValue::chosen('other'))?->minorUnits);
        self::assertSame(700, OptionDefinition::draw('loot', 'Loot', 'tables', $this->cad(700))->supplement(OptionValue::toDraw())?->minorUnits);
        self::assertNull(OptionDefinition::draw('loot', 'Loot', 'tables')->supplement(OptionValue::toDraw()));
    }

    public function testASetChecksAWholeConfiguration(): void
    {
        $set = OptionSet::of(
            OptionDefinition::text('name', 'Name', TextConstraints::of(10), supplement: $this->cad(500)),
            OptionDefinition::choice('stone', 'Stone', [OptionChoice::of('plain', 'Plain', supplement: $this->cad(200))]),
            OptionDefinition::draw('loot', 'Loot', 'tables'),
        );

        self::assertFalse($set->isEmpty());
        self::assertTrue(OptionSet::none()->isEmpty());
        self::assertSame([], OptionSet::none()->options());
        self::assertSame(['name', 'stone', 'loot'], array_map(static fn (OptionDefinition $option): string => $option->code, $set->options()));
        self::assertSame('Stone', $set->option('stone')?->label);
        self::assertNull($set->option('other'));

        $checked = $set->check(LineConfiguration::of(['stone' => OptionValue::chosen('plain')]));
        self::assertSame(['loot', 'stone'], array_keys($checked->values));
        self::assertSame('Plain', $checked->value('stone')?->display);
        self::assertTrue(OptionSet::none()->check(LineConfiguration::none())->isEmpty());

        $supplements = $set->supplements(LineConfiguration::of(['name' => OptionValue::chosen('Al'), 'stone' => OptionValue::chosen('plain'), 'gone' => OptionValue::chosen('x')]));
        self::assertSame([['name', 500], ['stone', 200]], array_map(static fn (array $supplement): array => [$supplement[0]->code, $supplement[1]->minorUnits], $supplements));
        self::assertSame([], $set->supplements(LineConfiguration::none()));

        try {
            $set->check(LineConfiguration::of(['colour' => OptionValue::chosen('red')]));
            self::fail('An unknown option was accepted.');
        } catch (InvalidConfiguration $invalidConfiguration) {
            self::assertSame('colour', $invalidConfiguration->option);
            self::assertSame("The option colour is not one of the sellable's.", $invalidConfiguration->getMessage());
        }
    }

    public function testAnOptionIsDeclaredOnceInASet(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The option name is declared twice.');

        OptionSet::of(OptionDefinition::draw('name', 'A', 'x'), OptionDefinition::draw('name', 'B', 'y'));
    }

    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function invalidDeclarations(): iterable
    {
        $cad = static fn (int $minor): Money => Money::of($minor, Currency::of('CAD'));
        $choice = OptionChoice::of('a', 'A');

        yield 'a code with a capital' => [static fn (): mixed => OptionChoice::of('A', 'A'), '"A" is not the code of a choice: 1 to 64 lowercase letters, digits, "_" or "-".'];
        yield 'a code starting with a dash' => [static fn (): mixed => OptionChoice::of('-a', 'A'), '"-a" is not the code of a choice'];
        yield 'a code too long' => [static fn (): mixed => OptionChoice::of(str_repeat('a', 65), 'A'), 'is not the code of a choice'];
        yield 'a choice code with a trailing line' => [static fn (): mixed => OptionChoice::of("a\n", 'A'), 'is not the code of a choice'];
        yield 'a blank choice label' => [static fn (): mixed => OptionChoice::of('a', ' '), 'The choice a has no label.'];
        yield 'an image with a space' => [static fn (): mixed => OptionChoice::of('a', 'A', 'a b.png'), 'The image of the choice a is not a path or URL: 1 to 2048 visible ASCII characters.'];
        yield 'an empty image' => [static fn (): mixed => OptionChoice::of('a', 'A', ''), 'The image of the choice a is not a path or URL'];
        yield 'an image with a trailing line' => [static fn (): mixed => OptionChoice::of('a', 'A', "a.png\n"), 'The image of the choice a is not a path or URL'];
        yield 'an image too long' => [static fn (): mixed => OptionChoice::of('a', 'A', str_repeat('a', 2049)), 'The image of the choice a is not a path or URL'];
        yield 'a negative choice supplement' => [static fn (): mixed => OptionChoice::of('a', 'A', null, $cad(-1)), 'The choice a has a negative supplement; a discount is a promotion.'];
        yield 'a zone name with a capital' => [static fn (): mixed => PrintZone::of('Chest', 0, 0, 1, 1), '"Chest" is not the name of a print zone: up to 32 lowercase letters, digits or "_", starting with a letter.'];
        yield 'a zone name too long' => [static fn (): mixed => PrintZone::of('a'.str_repeat('b', 32), 0, 0, 1, 1), 'is not the name of a print zone'];
        yield 'a zone name with a trailing line' => [static fn (): mixed => PrintZone::of("chest\n", 0, 0, 1, 1), 'is not the name of a print zone'];
        yield 'a zone left of the area' => [static fn (): mixed => PrintZone::of('chest', -1, 0, 1, 1), 'The print zone chest starts outside the printable area.'];
        yield 'a zone above the area' => [static fn (): mixed => PrintZone::of('chest', 0, -1, 1, 1), 'The print zone chest starts outside the printable area.'];
        yield 'a zone without width' => [static fn (): mixed => PrintZone::of('chest', 0, 0, 0, 1), 'The print zone chest has no area: its width and height are at least one millimetre.'];
        yield 'a zone without height' => [static fn (): mixed => PrintZone::of('chest', 0, 0, 1, 0), 'The print zone chest has no area'];
        yield 'a minimum of zero' => [static fn (): mixed => TextConstraints::of(5, 0), 'A text holds from 1 to 1000 characters, at least its minimum; 0 to 5 is not that.'];
        yield 'a maximum below the minimum' => [static fn (): mixed => TextConstraints::of(2, 3), '3 to 2 is not that.'];
        yield 'a maximum too large' => [static fn (): mixed => TextConstraints::of(1001), '1 to 1001 is not that.'];
        yield 'an empty character class' => [static fn (): mixed => TextConstraints::of(5, characters: ''), '"" is not a character class.'];
        yield 'a broken character class' => [static fn (): mixed => TextConstraints::of(5, characters: '\\'), '"\\" is not a character class.'];
        yield 'a font with a space around' => [static fn (): mixed => TextConstraints::of(5, font: ' Serif'), '" Serif" is not the name of a font: 1 to 128 characters, without spaces around.'];
        yield 'a font ending with a space' => [static fn (): mixed => TextConstraints::of(5, font: 'Serif '), 'is not the name of a font'];
        yield 'an empty font' => [static fn (): mixed => TextConstraints::of(5, font: ''), 'is not the name of a font'];
        yield 'a font with a trailing line' => [static fn (): mixed => TextConstraints::of(5, font: "Serif\n"), 'is not the name of a font'];
        yield 'a font too long' => [static fn (): mixed => TextConstraints::of(5, font: str_repeat('a', 129)), 'is not the name of a font'];
        yield 'no source' => [static fn (): mixed => TextConstraints::of(5, sources: []), 'A text option has its sources, each once.'];
        yield 'a source twice' => [static fn (): mixed => TextConstraints::of(5, sources: [TextSource::Free, TextSource::Free]), 'A text option has its sources, each once.'];
        yield 'a list without entries' => [static fn (): mixed => TextConstraints::of(5, sources: [TextSource::List]), 'A text option picked or drawn from a list has its entries, and only then.'];
        yield 'a draw without entries' => [static fn (): mixed => TextConstraints::of(5, sources: [TextSource::Drawn]), 'A text option picked or drawn from a list has its entries, and only then.'];
        yield 'entries without a list' => [static fn (): mixed => TextConstraints::of(5, entries: ['a']), 'A text option picked or drawn from a list has its entries, and only then.'];
        yield 'a blank entry' => [static fn (): mixed => TextConstraints::of(5, sources: [TextSource::List], entries: ['a', ' ']), 'An entry of a text option is not blank.'];
        yield 'an entry twice' => [static fn (): mixed => TextConstraints::of(5, sources: [TextSource::List], entries: ['a', 'a']), 'The entries of a text option are each listed once.'];
        yield 'a drawn text without a drawer' => [static fn (): mixed => OptionDefinition::text('a', 'A', TextConstraints::of(5, sources: [TextSource::Drawn], entries: ['a'])), 'The text option a names a drawer when it may be drawn, and only then.'];
        yield 'a typed text with a drawer' => [static fn (): mixed => OptionDefinition::text('a', 'A', TextConstraints::of(5), drawer: 'x'), 'The text option a names a drawer when it may be drawn, and only then.'];
        yield 'an option code with a capital' => [static fn (): mixed => OptionDefinition::draw('A', 'A', 'x'), '"A" is not the code of an option: up to 32 lowercase letters, digits or "_", starting with a letter.'];
        yield 'a blank option label' => [static fn (): mixed => OptionDefinition::draw('a', ' ', 'x'), 'The option a has no label.'];
        yield 'a negative option supplement' => [static fn (): mixed => OptionDefinition::draw('a', 'A', 'x', $cad(-1)), 'The option a has a negative supplement; a discount is a promotion.'];
        yield 'a drawer with a space' => [static fn (): mixed => OptionDefinition::draw('a', 'A', 'x y'), '"x y" is not the name of a drawer: 1 to 64 visible ASCII characters.'];
        yield 'a drawer too long' => [static fn (): mixed => OptionDefinition::draw('a', 'A', str_repeat('x', 65)), 'is not the name of a drawer'];
        yield 'a drawer with a trailing line' => [static fn (): mixed => OptionDefinition::draw('a', 'A', "x\n"), 'is not the name of a drawer'];
        yield 'a choice option without choices' => [static fn (): mixed => OptionDefinition::choice('a', 'A', []), 'The option a has at least one choice.'];
        yield 'an ink option without inks' => [static fn (): mixed => OptionDefinition::ink('a', 'A', []), 'The option a has at least one choice.'];
        yield 'a choice twice' => [static fn (): mixed => OptionDefinition::choice('a', 'A', [$choice, $choice]), 'The option a lists the choice a twice.'];
    }

    /**
     * @param Closure(): mixed $declare
     */
    #[DataProvider('invalidDeclarations')]
    public function testAnInvalidDeclarationIsRefused(Closure $declare, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        $declare();
    }

    public function testTheBoundsOfADeclarationAreAccepted(): void
    {
        self::assertSame(str_repeat('a', 64), OptionChoice::of(str_repeat('a', 64), 'A')->code);
        self::assertSame('0_-', OptionChoice::of('0_-', 'A')->code);
        self::assertSame(str_repeat('a', 2048), OptionChoice::of('a', 'A', str_repeat('a', 2048))->image);
        self::assertSame(0, OptionChoice::of('a', 'A', null, $this->cad(0))->supplement?->minorUnits);
        self::assertSame('a'.str_repeat('b', 31), PrintZone::of('a'.str_repeat('b', 31), 0, 0, 1, 1)->name);
        self::assertSame(1000, TextConstraints::of(1000, 1000)->maxLength);
        self::assertSame(1, TextConstraints::of(1)->minLength);
        self::assertSame(str_repeat('a', 128), TextConstraints::of(5, font: str_repeat('a', 128))->font);
        self::assertSame('A', TextConstraints::of(5, font: 'A')->font);
        self::assertSame(str_repeat('é', 128), TextConstraints::of(5, font: str_repeat('é', 128))->font, 'the length of a font is counted in characters');
        self::assertSame(str_repeat('x', 64), OptionDefinition::draw('a', 'A', str_repeat('x', 64))->drawer);
        self::assertSame(0, OptionDefinition::draw('a', 'A', 'x', $this->cad(0))->supplement?->minorUnits);
    }

    private function assertRefused(OptionDefinition $option, OptionValue $value, string $reason): void
    {
        try {
            $option->check($value);
            self::fail('The value was accepted.');
        } catch (InvalidConfiguration $invalidConfiguration) {
            self::assertSame($option->code, $invalidConfiguration->option);
            self::assertSame(\sprintf('The value of the option %s is refused: %s.', $option->code, $reason), $invalidConfiguration->getMessage());
        }
    }

    private function cad(int $minor): Money
    {
        return Money::of($minor, Currency::of('CAD'));
    }
}
