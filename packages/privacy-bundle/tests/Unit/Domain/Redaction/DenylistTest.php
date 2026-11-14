<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Redaction;

use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Redaction\Denylist;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Denylist::class)]
final class DenylistTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function spellingsOfTheSameKey(): iterable
    {
        yield 'camel case' => ['totpSecret'];
        yield 'snake case' => ['totp_secret'];
        yield 'header case' => ['TOTP-Secret'];
        yield 'dotted' => ['totp.secret'];
        yield 'padded' => [' totp secret '];
        yield 'surrounded by whitespace' => ["\ttotp_secret\n"];
    }

    #[DataProvider('spellingsOfTheSameKey')]
    public function testAKeyIsDeniedWhateverItsCaseAndSeparators(string $key): void
    {
        self::assertTrue(Denylist::of('totp_secret')->denies($key));
    }

    public function testAHeaderIsDeniedInAnyCase(): void
    {
        $denylist = Denylist::of('Authorization', 'Set-Cookie');

        self::assertTrue($denylist->denies('authorization'));
        self::assertTrue($denylist->denies('set_cookie'));
    }

    /**
     * Not a substring search: `code` would otherwise take `postal_code` with it.
     */
    public function testACompoundOfADeniedKeyIsNotDenied(): void
    {
        $denylist = Denylist::of('code');

        self::assertTrue($denylist->denies('code'));
        self::assertFalse($denylist->denies('postal_code'));
    }

    public function testTheKeysAreNormalisedDeduplicatedAndSorted(): void
    {
        self::assertSame(['password', 'totpsecret'], Denylist::of('totpSecret', 'password', 'totp_secret')->keys());
    }

    public function testANumericKeyReadsBackAsAString(): void
    {
        self::assertSame(['123', 'pin'], Denylist::fromList(['pin', '1-2-3'])->keys());
    }

    public function testAnEmptyDenylistDeniesNothing(): void
    {
        self::assertFalse(Denylist::fromList([])->denies('password'));
    }

    public function testAKeyThatIsOnlySeparatorsIsRefused(): void
    {
        $this->expectException(InvalidPrivacyValue::class);

        Denylist::of('password', ' _- ');
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function patterns(): iterable
    {
        yield 'a prefix pattern takes the stem itself' => ['seed*', 'seed', true];
        yield 'a prefix pattern takes a key that starts with the stem' => ['seed*', 'seedPhrase', true];
        yield 'a prefix pattern leaves a key that ends with the stem' => ['seed*', 'totp_seed', false];
        yield 'a prefix pattern leaves a key that only contains the stem' => ['seed*', 'theseedling', false];
        yield 'a suffix pattern takes the stem itself' => ['*seed', 'seed', true];
        yield 'a suffix pattern takes a key that ends with the stem' => ['*seed', 'recovery-seed', true];
        yield 'a suffix pattern leaves a key that starts with the stem' => ['*seed', 'seedPhrase', false];
        yield 'a suffix pattern leaves a key that only contains the stem' => ['*seed', 'theseedling', false];
        yield 'a fragment pattern takes the stem itself' => ['*seed*', 'seed', true];
        yield 'a fragment pattern takes a key that starts with the stem' => ['*seed*', 'seed_phrase', true];
        yield 'a fragment pattern takes a key that ends with the stem' => ['*seed*', 'TotpSeed', true];
        yield 'a fragment pattern takes a key that contains the stem' => ['*seed*', 'recovery.seed.backup', true];
        yield 'a fragment pattern leaves a key without the stem' => ['*seed*', 'secret', false];
        yield 'the stem is normalised like a key' => ['*_Seed', 'totpseed', true];
        yield 'a key is normalised before it meets a pattern' => ['seed*', 'SEED-phrase', true];
        yield 'a padded pattern is still a pattern' => [" *seed \n", 'totp_seed', true];
        yield 'a two-character pattern is a pattern' => ['a*', 'apple', true];
    }

    #[DataProvider('patterns')]
    public function testAPatternMatchesItsFamilyOfKeys(string $pattern, string $key, bool $denied): void
    {
        self::assertSame($denied, Denylist::of('password', $pattern)->denies($key));
    }

    /**
     * Patterns are opt-in: a list of exact keys keeps comparing exactly.
     */
    public function testAnExactKeyDoesNotBecomeAPattern(): void
    {
        $denylist = Denylist::of('seed');

        self::assertTrue($denylist->denies('seed'));
        self::assertFalse($denylist->denies('seedPhrase'));
        self::assertFalse($denylist->denies('totp_seed'));
    }

    public function testPatternsReadBackWithTheirStarsSortedAmongTheKeys(): void
    {
        self::assertSame(
            ['*code*', '*seed', 'password', 'seed*'],
            Denylist::of('seed*', '*_Seed', 'password', '*code*', 'seed*', '*seed')->keys(),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedPatterns(): iterable
    {
        yield 'a lone star' => ['*'];
        yield 'two stars' => ['**'];
        yield 'stars around separators' => ['*_-*'];
        yield 'a star inside the stem' => ['se*ed'];
        yield 'a third star' => ['*se*ed*'];
        yield 'a doubled leading star' => ['**seed'];
        yield 'a doubled trailing star' => ['seed**'];
    }

    #[DataProvider('malformedPatterns')]
    public function testAMalformedPatternIsRefused(string $pattern): void
    {
        $this->expectException(InvalidPrivacyValue::class);

        Denylist::of($pattern);
    }

    public function testAStarInsideTheStemIsRefusedWithItsReason(): void
    {
        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains('"se*ed" is not a denylist key: "*" may only open or close a pattern, such as "seed*", "*seed" or "*seed*".');

        Denylist::of('se*ed');
    }

    public function testAnEmptyPatternIsRefusedWithItsReason(): void
    {
        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains('"*-*" is not a denylist key: nothing is left once case, separators and pattern stars are dropped.');

        Denylist::of('*-*');
    }
}
