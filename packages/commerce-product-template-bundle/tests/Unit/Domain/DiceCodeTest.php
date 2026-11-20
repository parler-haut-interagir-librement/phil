<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Tests\Unit\Domain;

use Phil\CommerceProductTemplateBundle\Domain\ValueObject\DiceCode;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DiceCode::class)]
final class DiceCodeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, non-empty-list<int>, list<int>, string}>
     */
    public static function draws(): iterable
    {
        yield 'six d6 separated by spaces' => ['3 5 2 6 1 4', [6, 6, 6, 6, 6, 6], [3, 5, 2, 6, 1, 4], '352614'];
        yield 'six d6 written one character each' => ['352614', [6, 6, 6, 6, 6, 6], [3, 5, 2, 6, 1, 4], '352614'];
        yield 'a d10 in hexadecimal' => ['10,9', [10, 10], [10, 9], 'A9'];
        yield 'a d20 past hexadecimal' => ['20;11 - 1', [20, 20, 20], [20, 11, 1], 'KB1'];
        yield 'characters in lowercase' => ['ka', [20, 10], [20, 10], 'KA'];
        yield 'the one result of a single die as a number' => ['17', [20], [17], 'H'];
        yield 'the one result of a single die as a character' => ['H', [20], [17], 'H'];
        yield 'spaces around' => ['  2 2 ', [6, 6], [2, 2], '22'];
        yield 'every character' => ['1 2 3 4 5 6 7 8 9 10 11 12 13 14 15 16 17 18 19 20', array_fill(0, 20, 20), range(1, 20), '123456789ABCDEFGHIJK'];
        yield 'two-sided dice' => ['2 1', [2, 2], [2, 1], '21'];
        yield 'digits without a separator for two dice are two characters' => ['12', [20, 20], [1, 2], '12'];
    }

    /**
     * @param non-empty-list<int> $sides
     * @param list<int>           $results
     */
    #[DataProvider('draws')]
    public function testADrawReadsAsOneResultPerDie(string $draw, array $sides, array $results, string $code): void
    {
        $dice = DiceCode::read($draw, $sides);

        self::assertSame($results, $dice->results);
        self::assertSame($code, $dice->code());
    }

    /**
     * @return iterable<string, array{string, non-empty-list<int>, string}>
     */
    public static function refused(): iterable
    {
        yield 'too few results' => ['3 5', [6, 6, 6], 'The draw "3 5" gives 2 results for 3 dice.'];
        yield 'too many results' => ['3 5 1', [6, 6], 'The draw "3 5 1" gives 3 results for 2 dice.'];
        yield 'too many characters' => ['352', [6, 6], 'The draw "352" gives 0 results for 2 dice.'];
        yield 'a result past its die' => ['7 1', [6, 6], 'The draw "7 1" gives 7 on die 1, a d6.'];
        yield 'a character past its die' => ['1A', [6, 6], 'The draw "1A" gives 10 on die 2, a d6.'];
        yield 'a zero' => ['0 1', [6, 6], 'The draw "0 1" gives 0 on die 1, a d6.'];
        yield 'a number of three digits' => ['100 1', [20, 20], 'The draw "100 1" gives 0 results for 2 dice.'];
        yield 'a character outside the alphabet' => ['1L', [20, 20], 'The draw "1L" gives 0 results for 2 dice.'];
        yield 'a character outside the alphabet before the others' => ['!35', [6, 6, 6], 'The draw "!35" gives 0 results for 3 dice.'];
        yield 'nothing' => ['', [6], 'The draw "" gives 0 results for 1 dice.'];
    }

    /**
     * @param non-empty-list<int> $sides
     */
    #[DataProvider('refused')]
    public function testADrawThatDoesNotReadIsRefused(string $draw, array $sides, string $message): void
    {
        $this->expectExceptionObject(InvalidValue::because($message));

        DiceCode::read($draw, $sides);
    }

    public function testADieOutsideTwoToTwentyFacesIsRefused(): void
    {
        foreach ([1, 21] as $sides) {
            try {
                DiceCode::read('1', [$sides]);
                self::fail('The die is refused.');
            } catch (InvalidValue $invalidValue) {
                self::assertSame(\sprintf('A die has 2 to 20 faces, not %d.', $sides), $invalidValue->getMessage());
            }
        }
    }
}
