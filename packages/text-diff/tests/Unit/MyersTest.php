<?php

declare(strict_types=1);

namespace Phil\TextDiff\Tests\Unit;

use Phil\TextDiff\Myers;
use Phil\TextDiff\Operation;
use Phil\TextDiff\OperationKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Myers::class)]
#[CoversClass(Operation::class)]
final class MyersTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function scripts(): iterable
    {
        yield 'both empty' => ['', '', []];
        yield 'identical' => ['abc', 'abc', ['=a|b|c']];
        yield 'all inserted' => ['', 'ab', ['+a|b']];
        yield 'all deleted' => ['ab', '', ['-a|b']];
        yield 'inserted at the start' => ['bc', 'abc', ['+a', '=b|c']];
        yield 'inserted at the end' => ['ab', 'abc', ['=a|b', '+c']];
        yield 'inserted in the middle' => ['ac', 'abc', ['=a', '+b', '=c']];
        yield 'deleted in the middle' => ['abc', 'ac', ['=a', '-b', '=c']];
        yield 'replaced, deletion first' => ['axc', 'ayc', ['=a', '-x', '+y', '=c']];
        yield 'replaced whole' => ['ab', 'cd', ['-a|b', '+c|d']];
        yield 'Myers paper example' => ['abcabba', 'cbabac', ['-a|b', '=c', '+b', '=a|b', '-b', '=a', '+c']];
        yield 'repeated tokens' => ['aaa', 'aa', ['=a|a', '-a']];
        yield 'prefix and suffix overlap' => ['aba', 'aa', ['=a', '-b', '=a']];
        yield 'two changes' => ['abcde', 'xbcdy', ['-a', '+x', '=b|c|d', '-e', '+y']];
        yield 'inner equal after trimming' => ['xaby', 'zabw', ['-x', '+z', '=a|b', '-y', '+w']];
        yield 'insert then delete in one change' => ['abxd', 'aybd', ['=a', '+y', '=b', '-x', '=d']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('scripts')]
    public function testFindsTheShortestScript(string $old, string $new, array $expected): void
    {
        self::assertSame($expected, Operations::describe(new Myers(1000)->diff($this->split($old), $this->split($new))));
    }

    /**
     * @return iterable<string, array{list<string>, list<string>, list<string>}>
     */
    public static function spaces(): iterable
    {
        yield 'a space between two changes joins them' => [['a', ' ', 'b'], ['c', ' ', 'd'], ['-a| |b', '+c| |d']];
        yield 'a space at the start stays' => [[' ', 'a'], [' ', 'b'], ['= ', '-a', '+b']];
        yield 'a space at the end stays' => [['a', ' '], ['b', ' '], ['-a', '+b', '= ']];
        yield 'a word between two changes stays' => [['a', 'x', 'b'], ['c', 'x', 'd'], ['-a', '+c', '=x', '-b', '+d']];
        yield 'spaces of several tokens' => [['a', ' ', "\n", 'b', 'k'], ['c', ' ', "\n", 'd', 'k'], ['-a| |'."\n".'|b', '+c| |'."\n".'|d', '=k']];
        yield 'a space before a last change joins it' => [['a', ' '], ['c', ' ', 'd'], ['-a| ', '+c| |d']];
        yield 'space then word' => [['a', ' ', 'x', 'b'], ['c', ' ', 'x', 'd'], ['-a', '+c', '= |x', '-b', '+d']];
    }

    /**
     * @param list<string> $old
     * @param list<string> $new
     * @param list<string> $expected
     */
    #[DataProvider('spaces')]
    public function testWhiteSpaceBetweenTwoChangesJoinsThem(array $old, array $new, array $expected): void
    {
        self::assertSame($expected, Operations::describe(new Myers(1000)->diff($old, $new)));
    }

    public function testPastTheBoundTheMiddleIsReplacedWhole(): void
    {
        self::assertSame(['=s', '-a|x|b', '+c|x|d', '=e'], Operations::describe(new Myers(3)->diff($this->split('saxbe'), $this->split('scxde'))));
        self::assertSame(['=s', '-a', '+c', '=x', '-b', '+d', '=e'], Operations::describe(new Myers(4)->diff($this->split('saxbe'), $this->split('scxde'))));
        self::assertSame(['=a', '-b', '+c', '=d'], Operations::describe(new Myers(0)->diff($this->split('abd'), $this->split('acd'))));
        self::assertSame(['=a|b'], Operations::describe(new Myers(0)->diff($this->split('ab'), $this->split('ab'))));
        self::assertSame(['=a', '+b'], Operations::describe(new Myers(1)->diff($this->split('a'), $this->split('ab'))));
    }

    public function testEveryScriptIsShortestAndGivesBothListsBack(): void
    {
        foreach ([...$this->pairs('ab', 5), ...$this->pairs('abc', 3)] as [$old, $new]) {
            $operations = new Myers(1000)->diff($old, $new);
            $kept = 0;
            $previous = null;

            foreach ($operations as $operation) {
                self::assertNotSame([], $operation->tokens);
                self::assertNotSame($previous, $operation->kind);
                self::assertFalse(OperationKind::Delete === $operation->kind && OperationKind::Insert === $previous);
                $kept += OperationKind::Equal === $operation->kind ? \count($operation->tokens) : 0;
                $previous = $operation->kind;
            }

            self::assertSame($old, $this->side($operations, OperationKind::Delete));
            self::assertSame($new, $this->side($operations, OperationKind::Insert));
            self::assertSame($this->lcs($old, $new), $kept, implode('', $old).' / '.implode('', $new));
        }
    }

    public function testOperationText(): void
    {
        self::assertSame('ab c', new Operation(OperationKind::Equal, ['ab', ' ', 'c'])->text());
    }

    /**
     * @return list<string>
     */
    private function split(string $text): array
    {
        return '' === $text ? [] : str_split($text);
    }

    /**
     * Every pair of token lists of at most `$length` tokens of `$alphabet`.
     *
     * @return list<array{list<string>, list<string>}>
     */
    private function pairs(string $alphabet, int $length): array
    {
        $lists = [[]];
        $last = [[]];

        for ($size = 1; $size <= $length; ++$size) {
            $next = [];

            foreach ($last as $list) {
                foreach (str_split($alphabet) as $token) {
                    $next[] = [...$list, $token];
                }
            }

            array_push($lists, ...$next);
            $last = $next;
        }

        $pairs = [];

        foreach ($lists as $old) {
            foreach ($lists as $new) {
                $pairs[] = [$old, $new];
            }
        }

        return $pairs;
    }

    /**
     * @param list<Operation> $operations
     *
     * @return list<string>
     */
    private function side(array $operations, OperationKind $kind): array
    {
        $tokens = [];

        foreach ($operations as $operation) {
            if ($kind === $operation->kind || OperationKind::Equal === $operation->kind) {
                array_push($tokens, ...$operation->tokens);
            }
        }

        return $tokens;
    }

    /**
     * @param list<string> $old
     * @param list<string> $new
     */
    private function lcs(array $old, array $new): int
    {
        $row = array_fill(0, \count($new) + 1, 0);

        foreach ($old as $token) {
            $previous = 0;

            foreach ($new as $index => $other) {
                $current = $row[$index + 1];
                $row[$index + 1] = $token === $other ? $previous + 1 : max($row[$index + 1], $row[$index]);
                $previous = $current;
            }
        }

        return $row[\count($new)];
    }
}
