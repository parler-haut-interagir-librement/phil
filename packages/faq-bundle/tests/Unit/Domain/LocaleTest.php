<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Tests\Unit\Domain;

use Phil\FaqBundle\Domain\Exception\InvalidFaq;
use Phil\FaqBundle\Domain\ValueObject\Locale;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Locale::class)]
#[CoversClass(InvalidFaq::class)]
final class LocaleTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, list<string>, string}>
     */
    public static function locales(): iterable
    {
        yield 'a language' => ['fr', 'fr', 'fr', ['fr'], 'fr'];
        yield 'a region' => ['fr_CA', 'fr_CA', 'fr', ['fr_CA', 'fr'], 'fr-CA'];
        yield 'a hyphen' => ['fr-CA', 'fr_CA', 'fr', ['fr_CA', 'fr'], 'fr-CA'];
        yield 'three letters and a numeric region' => ['ast_419', 'ast_419', 'ast', ['ast_419', 'ast'], 'ast-419'];
    }

    /**
     * @param list<string> $chain
     */
    #[DataProvider('locales')]
    public function testALocaleIsReadInTheTranslatorSpelling(string $given, string $value, string $language, array $chain, string $tag): void
    {
        $locale = Locale::fromString($given);

        self::assertSame([$value, $language, $chain, $tag, $value], [$locale->value, $locale->language, $locale->chain(), $locale->tag(), (string) $locale]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalid(): iterable
    {
        yield 'empty' => [''];
        yield 'capitals' => ['FR'];
        yield 'a lowercase region' => ['fr_ca'];
        yield 'one letter' => ['f'];
        yield 'four letters' => ['fren'];
        yield 'a trailing line' => ["fr\n"];
        yield 'a script' => ['sr_Latn'];
    }

    #[DataProvider('invalid')]
    public function testWhatIsNotALocaleIsRefused(string $given): void
    {
        $this->expectExceptionObject(InvalidFaq::because('A locale is a language, and maybe a region: "fr" or "fr_CA".'));

        Locale::fromString($given);
    }
}
