<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Unit\Domain;

use Phil\TermsBundle\Domain\Exception\InvalidTerms;
use Phil\TermsBundle\Domain\Identifiers;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Identifiers::class)]
#[CoversClass(InvalidTerms::class)]
final class IdentifiersTest extends TestCase
{
    public function testAPolicyKeyIsALowercaseWordOf64CharactersAtMost(): void
    {
        self::assertSame('terms', Identifiers::policy('terms'));
        self::assertSame('code_of-conduct2', Identifiers::policy('code_of-conduct2'));
        self::assertSame('a'.str_repeat('b', 63), Identifiers::policy('a'.str_repeat('b', 63)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'a digit first' => ['1terms'];
        yield 'uppercase' => ['Terms'];
        yield 'a space' => ['terms of use'];
        yield 'too long' => ['a'.str_repeat('b', 64)];
        yield 'a dot' => ['terms.v2'];
        yield 'a new line at the end' => ["terms\n"];
    }

    #[DataProvider('badKeys')]
    public function testABadPolicyKeyIsRefused(string $key): void
    {
        try {
            Identifiers::policy($key);
            self::fail('Accepted.');
        } catch (InvalidTerms $invalidTerms) {
            self::assertSame('policy_key', $invalidTerms->reason);
            self::assertSame(['%key%' => $key], $invalidTerms->parameters);
            self::assertStringContainsString('"'.$key.'" is not', $invalidTerms->getMessage());
        }
    }

    public function testTheKeyInTheMessageIsCutTo80Characters(): void
    {
        try {
            Identifiers::policy(str_repeat('é', 90));
            self::fail('Accepted.');
        } catch (InvalidTerms $invalidTerms) {
            self::assertSame(str_repeat('é', 80), $invalidTerms->parameters['%key%']);
        }
    }

    public function testAMemberIsAStableIdentifier(): void
    {
        self::assertSame('42', Identifiers::member('42'));
        self::assertSame('user@example.org', Identifiers::member('user@example.org'));
        self::assertSame(str_repeat('a', 190), Identifiers::member(str_repeat('a', 190)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badMembers(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('a', 191)];
        yield 'a space' => ['John Doe'];
        yield 'a tab' => ["a\tb"];
        yield 'a control character' => ["a\x01"];
        yield 'delete' => ["a\x7F"];
    }

    #[DataProvider('badMembers')]
    public function testABadMemberIsRefused(string $member): void
    {
        $this->expectExceptionObject(InvalidTerms::because('member', 'A member identifier holds 1 to 190 bytes, without white space or control characters.'));

        Identifiers::member($member);
    }

    public function testALocaleIsALanguageAndSubtags(): void
    {
        self::assertSame('fr', Identifiers::locale('fr'));
        self::assertSame('fr_CA', Identifiers::locale('fr_CA'));
        self::assertSame('zh-Hant-TW', Identifiers::locale('zh-Hant-TW'));
        self::assertSame('ast', Identifiers::locale('ast'));
        self::assertSame('en'.str_repeat('_a1b2c3d4', 3).'_abcde', Identifiers::locale('en'.str_repeat('_a1b2c3d4', 3).'_abcde'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badLocales(): iterable
    {
        yield 'empty' => [''];
        yield 'one letter' => ['f'];
        yield 'four letters' => ['fren'];
        yield 'uppercase language' => ['FR'];
        yield 'a short subtag' => ['fr_C'];
        yield 'a long subtag' => ['fr_abcdefghi'];
        yield 'too long' => ['en'.str_repeat('_a1b2c3d4', 3).'_abcdef'];
        yield 'a path' => ['../fr'];
        yield 'a new line at the end' => ["fr\n"];
    }

    #[DataProvider('badLocales')]
    public function testABadLocaleIsRefused(string $locale): void
    {
        try {
            Identifiers::locale($locale);
            self::fail('Accepted.');
        } catch (InvalidTerms $invalidTerms) {
            self::assertSame('locale', $invalidTerms->reason);
            self::assertSame('"'.$locale.'" is not a locale.', $invalidTerms->getMessage());
        }
    }

    public function testTheLocaleInTheMessageIsCutTo40Characters(): void
    {
        try {
            Identifiers::locale(str_repeat('é', 50));
            self::fail('Accepted.');
        } catch (InvalidTerms $invalidTerms) {
            self::assertSame(str_repeat('é', 40), $invalidTerms->parameters['%locale%']);
        }
    }
}
