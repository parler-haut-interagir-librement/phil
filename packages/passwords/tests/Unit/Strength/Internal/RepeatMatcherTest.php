<?php

declare(strict_types=1);

namespace Phil\Passwords\Tests\Unit\Strength\Internal;

use Phil\Passwords\Strength\Internal\RepeatMatcher;
use Phil\Passwords\Strength\Pattern;
use Phil\Passwords\Strength\PatternMatch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RepeatMatcher::class)]
final class RepeatMatcherTest extends TestCase
{
    public function testARepeatedCharacter(): void
    {
        self::assertSame([['aaaa', 0, 3, 'a', 4, 40.0]], $this->find('aaaa'));
        self::assertSame(Pattern::Repeat, RepeatMatcher::matches(['b', 'b'], static fn (array $characters): float => 1.0)[0]->pattern);
    }

    public function testTheShortestBaseOfTheLongestSpan(): void
    {
        self::assertSame([['abababab', 0, 7, 'ab', 4, 80.0]], $this->find('abababab'));
        self::assertSame([['abcabc', 1, 6, 'abc', 2, 60.0]], $this->find('xabcabcx'));
    }

    public function testThePositionAfterARepeatAndBetweenThem(): void
    {
        self::assertSame([['aa', 0, 1, 'a', 2, 20.0], ['bbb', 3, 5, 'b', 3, 30.0]], $this->find('aaxbbbc'));
        self::assertSame([['zz', 4, 5, 'z', 2, 20.0]], $this->find('abcdzz'));
    }

    public function testALongerSpanWinsOverAShorterOneFromTheSameStart(): void
    {
        // `a` twice from the first position covers 2, `aab` twice covers 6.
        self::assertSame([['aabaab', 0, 5, 'aab', 2, 60.0]], $this->find('aabaab'));
    }

    public function testARepeatThatStopsBeforeTheEnd(): void
    {
        self::assertSame([['abab', 0, 3, 'ab', 2, 40.0]], $this->find('ababa'));
    }

    public function testNoRepeat(): void
    {
        self::assertSame([], $this->find('abcdef'));
        self::assertSame([], $this->find('abca'));
        self::assertSame([], $this->find('a'));
        self::assertSame([], $this->find(''));
    }

    public function testTheNextRepeatStartsRightAfter(): void
    {
        self::assertSame([['abab', 0, 3, 'ab', 2, 40.0], ['bb', 4, 5, 'b', 2, 20.0]], $this->find('ababbb'));
    }

    public function testTheSpanOfTheBestRepeatSoFar(): void
    {
        // `a` three times covers 3; `aaab` twice covers 8.
        self::assertSame([['aaabaaab', 0, 7, 'aaab', 2, 80.0]], $this->find('aaabaaab'));
    }

    /**
     * @return list<array{string, int, int, string, int, float}>
     */
    private function find(string $password): array
    {
        $base = static fn (array $characters): float => 10.0 * \count($characters);

        return array_map(
            static fn (PatternMatch $match): array => [$match->token, $match->start, $match->end, (string) $match->baseToken, $match->repeatCount, $match->guesses],
            RepeatMatcher::matches(mb_str_split($password), $base),
        );
    }
}
