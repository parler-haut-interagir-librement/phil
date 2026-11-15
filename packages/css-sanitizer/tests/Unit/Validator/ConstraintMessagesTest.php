<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Tests\Unit\Validator;

use Phil\CssSanitizer\RemovalReason;
use Phil\CssSanitizer\Validator\SafeCss;
use Phil\CssSanitizer\Validator\SafeCssValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The constraint messages through a translator that reads the library's ICU catalogues, as FrameworkBundle does once
 * the application gives it their directory.
 */
#[CoversClass(SafeCss::class)]
#[CoversClass(SafeCssValidator::class)]
final class ConstraintMessagesTest extends TestCase
{
    private const string CATALOGUES = __DIR__.'/../../../translations';

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function counts(): iterable
    {
        yield 'en, one more' => ['en', 101, 'This CSS holds 1 more part that is not allowed.'];
        yield 'en, two more' => ['en', 102, 'This CSS holds 2 more parts that are not allowed.'];
        yield 'en, a thousand more' => ['en', 1100, 'This CSS holds 1,000 more parts that are not allowed.'];
        yield 'fr, one more' => ['fr', 101, 'Ce CSS contient 1 autre partie qui n’est pas permise.'];
        yield 'fr, two more' => ['fr', 102, 'Ce CSS contient 2 autres parties qui ne sont pas permises.'];
    }

    #[DataProvider('counts')]
    public function testTheCountAgreesWithItsNoun(string $locale, int $removals, string $expected): void
    {
        $violations = $this->validator($locale)->validate(str_repeat("a{x:1}\n", $removals), new SafeCss());
        $more = $violations[100];

        self::assertNotNull($more);
        self::assertSame($expected, $more->getMessage());
        self::assertSame(['{{ count }}' => $removals - 100], $more->getParameters());
        self::assertSame($removals - 100, $more->getPlural());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function removals(): iterable
    {
        yield 'en' => ['en', 'This CSS holds a property that is not allowed on line 1234: x.'];
        yield 'fr' => ['fr', 'Ce CSS contient une propriété qui n’est pas permise à la ligne 1234 : x.'];
    }

    #[DataProvider('removals')]
    public function testARemovalNamesItsLine(string $locale, string $expected): void
    {
        $violations = $this->validator($locale)->validate(str_repeat("\n", 1233).'a{x:1}', new SafeCss());

        self::assertCount(1, $violations);
        self::assertSame($expected, $violations[0]?->getMessage());
        self::assertSame(1234, $violations[0]->getParameters()['{{ line }}']);
    }

    public function testTheFrenchCatalogueNamesEveryReason(): void
    {
        $translator = $this->translator('fr');
        $message = new SafeCss()->message;
        $phrases = [];

        foreach (RemovalReason::cases() as $reason) {
            $translated = $translator->trans($message, ['{{ removed }}' => $reason->describe(), '{{ line }}' => 2, '{{ subject }}' => 's', '{{ reason }}' => $reason->value], 'validators');

            self::assertStringStartsWith('Ce CSS contient ', $translated);
            self::assertStringEndsWith(' à la ligne 2 : s.', $translated);
            self::assertStringNotContainsString($reason->describe(), $translated);
            $phrases[$translated] = true;
        }

        self::assertCount(\count(RemovalReason::cases()), $phrases);
        self::assertSame('Ce CSS contient something else à la ligne 2 : s.', $translator->trans($message, ['{{ removed }}' => 'something else', '{{ line }}' => 2, '{{ subject }}' => 's', '{{ reason }}' => 'other'], 'validators'));
    }

    public function testTheCataloguesTranslateTheDefaultMessages(): void
    {
        $constraint = new SafeCss();

        $french = require self::CATALOGUES.'/validators+intl-icu.fr.php';
        self::assertIsArray($french);
        self::assertSame([$constraint->message, $constraint->moreMessage], array_keys($french));

        $english = require self::CATALOGUES.'/validators+intl-icu.en.php';
        self::assertIsArray($english);
        self::assertSame([$constraint->moreMessage], array_keys($english));
    }

    public function testWithoutTheCataloguesTheMessagesStayAsWritten(): void
    {
        $violations = Validation::createValidator()->validate(str_repeat("a{x:1}\n", 102), new SafeCss());

        self::assertSame('This CSS holds a property that is not allowed on line 1: x.', $violations[0]?->getMessage());
        self::assertSame('This CSS holds 2 more parts that are not allowed.', $violations[100]?->getMessage());
    }

    private function validator(string $locale): ValidatorInterface
    {
        return Validation::createValidatorBuilder()
            ->setTranslator($this->translator($locale))
            ->setTranslationDomain('validators')
            ->getValidator();
    }

    private function translator(string $locale): Translator
    {
        $translator = new Translator($locale);
        $translator->setFallbackLocales(['en']);
        $translator->addLoader('php', new PhpFileLoader());

        foreach (['en', 'fr'] as $catalogue) {
            $translator->addResource('php', self::CATALOGUES.'/validators+intl-icu.'.$catalogue.'.php', $catalogue, 'validators+intl-icu');
        }

        return $translator;
    }
}
