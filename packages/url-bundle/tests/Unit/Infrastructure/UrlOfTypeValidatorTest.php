<?php

declare(strict_types=1);

namespace Phil\UrlBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\Enums\Collection\EnumSet;
use Phil\Url\KnownUrlType;
use Phil\Url\TypedUrl;
use Phil\Url\Url;
use Phil\UrlBundle\Infrastructure\Validator\UrlOfType;
use Phil\UrlBundle\Infrastructure\Validator\UrlOfTypeValidator;
use Phil\UrlBundle\Tests\Unit\Fixtures\RegistryUrlType;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<UrlOfTypeValidator>
 */
#[CoversClass(UrlOfType::class)]
#[CoversClass(UrlOfTypeValidator::class)]
final class UrlOfTypeValidatorTest extends ConstraintValidatorTestCase
{
    #[Override]
    protected function createValidator(): UrlOfTypeValidator
    {
        return new UrlOfTypeValidator();
    }

    public function testNothingIsValid(): void
    {
        $this->validate(null, new UrlOfType());
        $this->validate('', new UrlOfType());

        $this->assertNoViolation();
    }

    public function testAnAddressOfAnAcceptedKindIsValid(): void
    {
        $constraint = new UrlOfType([KnownUrlType::Facebook, KnownUrlType::Instagram]);

        $this->validate('https://instagram.com/shop', $constraint);
        $this->validate(Url::fromString('https://facebook.com/shop'), $constraint);
        $this->validate(TypedUrl::of(KnownUrlType::Facebook, 'https://facebook.com/shop'), $constraint);
        $this->validate('https://example.com/', new UrlOfType());
        $this->validate('https://registry.example/', new UrlOfType(RegistryUrlType::class));
        $this->validate('https://instagram.com/shop', new UrlOfType(EnumSet::of(KnownUrlType::class, KnownUrlType::Instagram)));
        $this->validate('https://registry.example/', new UrlOfType([EnumSet::all(RegistryUrlType::class), KnownUrlType::X]));

        $this->assertNoViolation();
    }

    public function testAnAddressOfAnotherKindIsNot(): void
    {
        $this->validate('https://example.com/', new UrlOfType([KnownUrlType::Facebook, RegistryUrlType::Registry]));

        $this->buildViolation('This address is not one of the accepted kinds: {{ types }}.')
            ->setParameter('{{ types }}', 'Facebook, Registry')
            ->setCode(UrlOfType::NOT_ACCEPTED_ERROR)
            ->assertRaised();
    }

    public function testATypedUrlOfAnotherKindIsNot(): void
    {
        $this->validate(TypedUrl::of(KnownUrlType::Website, 'https://facebook.com/shop'), new UrlOfType(KnownUrlType::Facebook, typeMessage: 'Not {{ types }}.'));

        $this->buildViolation('Not {{ types }}.')
            ->setParameter('{{ types }}', 'Facebook')
            ->setCode(UrlOfType::NOT_ACCEPTED_ERROR)
            ->assertRaised();
    }

    public function testATypedUrlOfAnotherEnumIsNot(): void
    {
        $this->validate(TypedUrl::of(RegistryUrlType::Registry, 'https://registry.example/'), new UrlOfType([KnownUrlType::Website]));

        $this->buildViolation('This address is not one of the accepted kinds: {{ types }}.')
            ->setParameter('{{ types }}', 'Website')
            ->setCode(UrlOfType::NOT_ACCEPTED_ERROR)
            ->assertRaised();
    }

    public function testATextThatIsNoAddressIsInvalid(): void
    {
        $this->validate('example.com', new UrlOfType(message: 'Bad {{ value }}.'));

        $this->buildViolation('Bad {{ value }}.')
            ->setParameter('{{ value }}', '"example.com"')
            ->setCode(UrlOfType::INVALID_ERROR)
            ->assertRaised();
    }

    public function testTheConstraintHasItsDefaults(): void
    {
        $constraint = new UrlOfType(groups: ['social']);

        self::assertSame([null, 'This value is not a valid web address.', 'This address is not one of the accepted kinds: {{ types }}.', ['social']], [$constraint->types, $constraint->message, $constraint->typeMessage, $constraint->groups]);
        self::assertSame('INVALID_ERROR', UrlOfType::getErrorName(UrlOfType::INVALID_ERROR));
        self::assertSame('NOT_ACCEPTED_ERROR', UrlOfType::getErrorName(UrlOfType::NOT_ACCEPTED_ERROR));
    }

    public function testOnlyItsConstraintAndValuesAreTaken(): void
    {
        try {
            $this->validate('https://example.com/', new NotBlank());
            self::fail('Another constraint was taken.');
        } catch (UnexpectedTypeException) {
        }

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('Expected argument of type "string|Phil\\Url\\Url|Phil\\Url\\TypedUrl", "stdClass" given');

        $this->validate(new stdClass(), new UrlOfType());
    }
}
