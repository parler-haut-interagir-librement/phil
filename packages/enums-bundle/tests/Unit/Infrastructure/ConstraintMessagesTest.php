<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Infrastructure;

use Phil\Enums\Collection\EnumSet;
use Phil\EnumsBundle\Infrastructure\Validator\EnumCase;
use Phil\EnumsBundle\Infrastructure\Validator\EnumSetOf;
use Phil\EnumsBundle\Infrastructure\Validator\EnumSetOfValidator;
use Phil\EnumsBundle\Infrastructure\Validator\Transition;
use Phil\EnumsBundle\Tests\Unit\Fixture\Access;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The constraint messages through a translator that reads the bundle's ICU catalogues, as FrameworkBundle does once
 * the bundle has given it their directory.
 */
#[CoversClass(EnumSetOf::class)]
#[CoversClass(EnumSetOfValidator::class)]
final class ConstraintMessagesTest extends TestCase
{
    private const string CATALOGUES = __DIR__.'/../../../translations-icu';

    /**
     * @return iterable<string, array{string, int|null, int|null, int, string}>
     */
    public static function counts(): iterable
    {
        yield 'en, at least one' => ['en', 1, null, 0, 'You must select at least 1 choice.'];
        yield 'en, at least two' => ['en', 2, null, 1, 'You must select at least 2 choices.'];
        yield 'en, at most one' => ['en', null, 1, 2, 'You must select at most 1 choice.'];
        yield 'en, at most two' => ['en', null, 2, 3, 'You must select at most 2 choices.'];
        yield 'fr, at least one' => ['fr', 1, null, 0, 'Vous devez sélectionner au moins 1 choix.'];
        yield 'fr, at least two' => ['fr', 2, null, 1, 'Vous devez sélectionner au moins 2 choix.'];
        yield 'fr, at most one' => ['fr', null, 1, 2, 'Vous devez sélectionner au plus 1 choix.'];
        yield 'fr, at most two' => ['fr', null, 2, 3, 'Vous devez sélectionner au plus 2 choix.'];
    }

    #[DataProvider('counts')]
    public function testTheCountMessagesAgreeWithTheLimit(string $locale, ?int $min, ?int $max, int $cases, string $expected): void
    {
        $set = EnumSet::of(Access::class, ...\array_slice(Access::cases(), 0, $cases));
        $violations = $this->validator($locale)->validate($set, new EnumSetOf(Access::class, min: $min, max: $max));

        self::assertCount(1, $violations);
        self::assertSame($expected, $violations[0]?->getMessage());
        self::assertSame(['{{ count }}' => $cases, '{{ limit }}' => $min ?? $max], $violations[0]->getParameters());
        self::assertSame($min ?? $max, $violations[0]->getPlural());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function notAllowed(): iterable
    {
        yield 'en' => ['en', 'The value "Write" is not an allowed choice.'];
        yield 'fr' => ['fr', 'La valeur "Write" n’est pas un choix permis.'];
    }

    #[DataProvider('notAllowed')]
    public function testACaseOutsideTheSelectionIsNamed(string $locale, string $expected): void
    {
        $violations = $this->validator($locale)->validate(EnumSet::of(Access::class, Access::Write), new EnumSetOf(Access::class, only: [Access::Read]));

        self::assertCount(1, $violations);
        self::assertSame($expected, $violations[0]?->getMessage());
    }

    public function testTheFrenchCatalogueTranslatesEveryDefaultMessage(): void
    {
        $set = new EnumSetOf(Access::class);
        $transition = new Transition();
        $defaults = [
            $set->message,
            $set->notAllowedMessage,
            $set->minMessage,
            $set->maxMessage,
            new EnumCase(Access::class)->message,
            $transition->message,
            $transition->initialMessage,
        ];

        $french = require self::CATALOGUES.'/validators+intl-icu.fr.php';
        self::assertIsArray($french);
        self::assertEqualsCanonicalizing(array_values(array_unique($defaults)), array_keys($french));

        $english = require self::CATALOGUES.'/validators+intl-icu.en.php';
        self::assertIsArray($english);
        self::assertSame([$set->minMessage, $set->maxMessage], array_keys($english));

        $translator = $this->translator('fr');
        self::assertSame('Cette valeur n’est pas un ensemble de choix permis.', $translator->trans($set->message, [], 'validators'));
        self::assertSame('L’état ne peut pas passer de "Draft" à "Paid".', $translator->trans($transition->message, ['{{ from }}' => '"Draft"', '{{ to }}' => '"Paid"'], 'validators'));
        self::assertSame('L’état ne peut pas commencer à "Paid".', $translator->trans($transition->initialMessage, ['{{ to }}' => '"Paid"'], 'validators'));
    }

    public function testWithoutTheCataloguesTheDefaultMessagesStillAgree(): void
    {
        $validator = Validation::createValidatorBuilder()->getValidator();

        $one = $validator->validate(EnumSet::none(Access::class), new EnumSetOf(Access::class, min: 1));
        $two = $validator->validate(EnumSet::all(Access::class), new EnumSetOf(Access::class, max: 2));

        self::assertSame('You must select at least 1 choice.', $one[0]?->getMessage());
        self::assertSame('You must select at most 2 choices.', $two[0]?->getMessage());
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
