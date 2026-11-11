<?php

declare(strict_types=1);

namespace Phil\Passwords\Tests\Unit\Strength;

use Phil\Passwords\Strength\Dictionary;
use Phil\Passwords\Strength\Internal\Analyser;
use Phil\Passwords\Strength\KeyboardLayout;
use Phil\Passwords\Strength\Pattern;
use Phil\Passwords\Strength\PatternMatch;
use Phil\Passwords\Strength\Score;
use Phil\Passwords\Strength\StrengthEstimator;
use Phil\Passwords\Strength\Suggestion;
use Phil\Passwords\Strength\Warning;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(StrengthEstimator::class)]
#[CoversClass(Analyser::class)]
final class StrengthEstimatorTest extends TestCase
{
    public function testAnEmptyPassword(): void
    {
        $strength = new StrengthEstimator(referenceYear: 2026)->estimate('');

        self::assertSame(1.0, $strength->guesses);
        self::assertSame(Score::VeryWeak, $strength->score);
        self::assertSame([], $strength->sequence);
        self::assertSame([0, 0], [$strength->length, $strength->analysedLength]);
        self::assertSame([Suggestion::UseWords, Suggestion::NoSymbolsNeeded], $strength->feedback->suggestions);
    }

    public function testACommonPassword(): void
    {
        $strength = new StrengthEstimator(referenceYear: 2026)->estimate('password');

        self::assertSame(3.0, $strength->guesses);
        self::assertSame(Score::VeryWeak, $strength->score);
        self::assertSame([[Pattern::Dictionary, 'password', 'passwords']], $this->describe($strength->sequence));
        self::assertSame(Warning::TopTen, $strength->feedback->warning);
        self::assertSame(0.3, $strength->crackTimes->onlineUnthrottled);
    }

    public function testARepeatCostsItsBaseByTheEstimatorsOwnAnalysis(): void
    {
        $strength = new StrengthEstimator(referenceYear: 2026)->estimate('abcabc');

        // `abc` is a sequence of 12 guesses, 13 as a whole; twice.
        self::assertSame(27.0, $strength->guesses);
        self::assertSame([[Pattern::Repeat, 'abcabc', null]], $this->describe($strength->sequence));
    }

    public function testUserInputs(): void
    {
        $estimator = new StrengthEstimator(referenceYear: 2026);
        $strength = $estimator->estimate('jdupont77', ['J. Dupont', 'jdupont']);

        self::assertSame(Dictionary::USER_INPUTS, $strength->sequence[0]->dictionary);
        self::assertSame(Warning::UserInput, $strength->feedback->warning);
        self::assertGreaterThan($strength->guesses, $estimator->estimate('jdupont77')->guesses);

        $inputs = array_map(static fn (int $i): string => 'input'.$i, range(1, 32));
        self::assertSame(Dictionary::USER_INPUTS, $estimator->estimate('input32', [...$inputs, 'zzqx'])->sequence[0]->dictionary);
        self::assertNotSame(Dictionary::USER_INPUTS, $estimator->estimate('zzqx', [...$inputs, 'zzqx'])->sequence[0]->dictionary);
    }

    public function testOnlyTheFirstCharactersAreAnalysed(): void
    {
        $strength = new StrengthEstimator(referenceYear: 2026)->estimate(str_repeat('ab', 70));

        self::assertSame([140, 128], [$strength->length, $strength->analysedLength]);
        self::assertTrue($strength->isTruncated());
        self::assertSame(str_repeat('ab', 64), implode('', array_map(static fn (PatternMatch $match): string => $match->token, $strength->sequence)));
    }

    public function testCharactersNotBytes(): void
    {
        $strength = new StrengthEstimator(referenceYear: 2026)->estimate('été');

        self::assertSame([3, 3], [$strength->length, $strength->analysedLength]);
    }

    public function testInvalidUtf8IsReadWithItsBytesReplaced(): void
    {
        $strength = new StrengthEstimator(referenceYear: 2026)->estimate("pa\xffss");

        self::assertSame([[Pattern::Bruteforce, 'pa?ss', null]], $this->describe($strength->sequence));
    }

    public function testTheDictionariesLayoutsAndReferenceYearOfTheApplication(): void
    {
        $estimator = new StrengthEstimator([Dictionary::of('site', ['zork'])], [KeyboardLayout::keypad()], 2000);

        self::assertSame([[Pattern::Dictionary, 'zork', 'site']], $this->describe($estimator->estimate('zork')->sequence));
        self::assertSame([[Pattern::Bruteforce, 'qwerty', null]], $this->describe($estimator->estimate('qwerty')->sequence));
        self::assertSame(21.0, $estimator->estimate('2000')->guesses);
    }

    public function testTheCurrentYearByDefault(): void
    {
        $year = idate('Y');
        $strength = new StrengthEstimator([], [])->estimate((string) ($year - 30));

        self::assertSame([[Pattern::Year, (string) ($year - 30), null]], $this->describe($strength->sequence));
        self::assertSame(31.0, $strength->guesses);
    }

    /**
     * @return iterable<string, array{string, Score}>
     */
    public static function scores(): iterable
    {
        yield 'keyboard' => ['qwertyuiop', Score::VeryWeak];
        yield 'french word' => ['soleil', Score::VeryWeak];
        yield 'date' => ['14/03/1987', Score::Weak];
        yield 'leet' => ['P@ssw0rd', Score::VeryWeak];
        yield 'random' => ['x7#Kq!2mZp', Score::VeryStrong];
        yield 'passphrase' => ['dragon-canard-chuchoter-ecrire', Score::VeryStrong];
    }

    #[DataProvider('scores')]
    public function testScores(string $password, Score $score): void
    {
        self::assertSame($score, new StrengthEstimator(referenceYear: 2026)->estimate($password)->score);
    }

    /**
     * @param list<PatternMatch> $sequence
     *
     * @return list<array{Pattern, string, ?string}>
     */
    private function describe(array $sequence): array
    {
        return array_map(static fn (PatternMatch $match): array => [$match->pattern, $match->token, $match->dictionary], $sequence);
    }
}
