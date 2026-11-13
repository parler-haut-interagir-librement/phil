<?php

declare(strict_types=1);

namespace Phil\Citations\Tests\Unit\Internal;

use Phil\Citations\Internal\BibTeXNames;
use Phil\Citations\Model\Name;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(BibTeXNames::class)]
final class BibTeXNamesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<Name>}>
     */
    public static function names(): iterable
    {
        yield 'first last' => ['John Smith', [new Name('Smith', 'John')]];
        yield 'one word' => ['Plato', [new Name('Plato')]];
        yield 'last, first' => ['Smith, John A.', [new Name('Smith', 'John A.')]];
        yield 'von last, jr, first' => ['van der Berg, Jr., Jan', [new Name('Berg', 'Jan', null, 'van der', 'Jr.')]];
        yield 'first von last' => ['Ludwig van Beethoven', [new Name('Beethoven', 'Ludwig', null, 'van')]];
        yield 'first von von last last' => ['Jean de la Fontaine Smith', [new Name('Fontaine Smith', 'Jean', null, 'de la')]];
        yield 'von last, first' => ['de Gaulle, Charles', [new Name('Gaulle', 'Charles', null, 'de')]];
        yield 'two-word last, first' => ['Lloyd Webber, Andrew', [new Name('Lloyd Webber', 'Andrew')]];
        yield 'the last word is the last name' => ['von Neumann', [new Name('Neumann', null, null, 'von')]];
        yield 'braces protect a literal' => ['{World Health Organization}', [Name::literal('World Health Organization')]];
        yield 'braces protect a last name' => ['Jan {van Eyck}', [new Name('van Eyck', 'Jan')]];
        yield 'a braced word is not von' => ['Jan {de} Vries', [new Name('Vries', 'Jan de')]];
        yield 'accents' => ['Fran\\c{c}ois Truffaut and G{\\"o}del, Kurt', [new Name('Truffaut', 'François'), new Name('Gödel', 'Kurt')]];
        yield 'and in any case' => ['A Smith AND B Jones', [new Name('Smith', 'A'), new Name('Jones', 'B')]];
        yield 'and inside braces' => ['{Smith and Sons}', [Name::literal('Smith and Sons')]];
        yield 'others' => ['Smith, John and others', [new Name('Smith', 'John')]];
        yield 'new lines' => ["Smith,\n  John\nand\tJones, Bo", [new Name('Smith', 'John'), new Name('Jones', 'Bo')]];
        yield 'empty parts' => [' and  and Smith, John and ', [new Name('Smith', 'John')]];
        yield 'a name without a family' => ['{} and , John and Smith', [new Name('Smith')]];
        yield 'an empty first name' => ['Smith, ', [new Name('Smith')]];
        yield 'an empty suffix' => ['Smith, , John', [new Name('Smith', 'John')]];
        yield 'a stray closing brace' => ['Smith} and Jones', [new Name('Smith'), new Name('Jones')]];
        yield 'a lowercase first word' => ['de la Cruz', [new Name('Cruz', null, null, 'de la')]];
        yield 'digits are not lowercase' => ['1st Battalion', [new Name('Battalion', '1st')]];
    }

    /**
     * @param list<Name> $expected
     */
    #[DataProvider('names')]
    public function testItReadsNames(string $raw, array $expected): void
    {
        self::assertEquals($expected, BibTeXNames::parse($raw, 1_000));
    }

    public function testTheNamesAreBounded(): void
    {
        self::assertEquals([new Name('A'), new Name('B')], BibTeXNames::parse('A and B and C', 2));
        self::assertSame([], BibTeXNames::parse('A', 0));
    }
}
