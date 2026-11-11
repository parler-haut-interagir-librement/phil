<?php

declare(strict_types=1);

namespace Phil\PhoneBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\Phone\PhoneNumber;
use Phil\Phone\PhoneNumberType;
use Phil\PhoneBundle\Infrastructure\Validator\ValidPhoneNumber;
use Phil\PhoneBundle\Infrastructure\Validator\ValidPhoneNumberValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<ValidPhoneNumberValidator>
 */
#[CoversClass(ValidPhoneNumber::class)]
#[CoversClass(ValidPhoneNumberValidator::class)]
final class ValidPhoneNumberValidatorTest extends ConstraintValidatorTestCase
{
    #[Override]
    protected function createValidator(): ValidPhoneNumberValidator
    {
        return new ValidPhoneNumberValidator('CA');
    }

    public function testNothingIsValid(): void
    {
        $this->validate(null, new ValidPhoneNumber());
        $this->validate('', new ValidPhoneNumber());

        $this->assertNoViolation();
    }

    public function testANumberOfItsPlanIsValid(): void
    {
        $this->validate('(514) 555-0123', new ValidPhoneNumber());
        $this->validate('06 12 34 56 78', new ValidPhoneNumber(region: 'FR'));
        $this->validate(PhoneNumber::parse('+33 6 12 34 56 78'), new ValidPhoneNumber(types: [PhoneNumberType::Mobile], regions: ['fr', 'BE']));

        $this->assertNoViolation();
    }

    /**
     * @return iterable<string, array{string|PhoneNumber, ValidPhoneNumber, string}>
     */
    public static function invalid(): iterable
    {
        yield 'letters' => ['1-800-FLOWERS', new ValidPhoneNumber(types: [PhoneNumberType::Mobile]), '"1-800-FLOWERS"'];
        yield 'in no plan' => ['514 5551', new ValidPhoneNumber(regions: ['CA']), '"514 5551"'];
        yield 'a parsed number in no plan' => [PhoneNumber::parse('+33 0 12 34 56 78'), new ValidPhoneNumber(), 'object'];
        yield 'read in the constraints region' => ['(514) 555-0123', new ValidPhoneNumber(region: 'FR'), '"(514) 555-0123"'];
    }

    #[DataProvider('invalid')]
    public function testWhatIsInNoPlanIsInvalid(string|PhoneNumber $value, ValidPhoneNumber $constraint, string $shown): void
    {
        $this->validate($value, $constraint);

        $this->buildViolation('This value is not a valid phone number.')
            ->setParameter('{{ value }}', $shown)
            ->setCode(ValidPhoneNumber::INVALID_ERROR)
            ->assertRaised();
    }

    public function testANumberOfAnotherTypeIsNot(): void
    {
        $this->validate('+33 1 23 45 67 89', new ValidPhoneNumber(types: [PhoneNumberType::Mobile], typeMessage: 'Not a mobile.'));

        $this->buildViolation('Not a mobile.')
            ->setCode(ValidPhoneNumber::TYPE_ERROR)
            ->assertRaised();
    }

    public function testANumberOfAnotherCountryIsNot(): void
    {
        $this->validate('+33 6 12 34 56 78', new ValidPhoneNumber(regions: ['CA', 'US']));

        $this->buildViolation('This phone number is not from an accepted country: {{ regions }}.')
            ->setParameter('{{ regions }}', 'CA, US')
            ->setCode(ValidPhoneNumber::REGION_ERROR)
            ->assertRaised();
    }

    /**
     * The regions are those of the constraint at hand, from one constraint to the next and back.
     */
    public function testEachConstraintAcceptsItsOwnRegions(): void
    {
        $french = new ValidPhoneNumber(regions: ['fr']);
        $american = new ValidPhoneNumber(regions: ['ca', 'US']);

        $this->validate('+33 6 12 34 56 78', $french);
        $this->validate('+33 6 12 34 56 79', $french);
        $this->validate('+1 514-555-0123', $american);
        $this->validate('+1 212-555-0123', $american);
        $this->validate('+33 6 12 34 56 78', $american);
        $this->validate('+33 6 12 34 56 78', $french);
        $this->validate('+1 514-555-0123', $french);

        $this->buildViolation('This phone number is not from an accepted country: {{ regions }}.')
            ->setParameter('{{ regions }}', 'ca, US')
            ->setCode(ValidPhoneNumber::REGION_ERROR)
            ->buildNextViolation('This phone number is not from an accepted country: {{ regions }}.')
            ->setParameter('{{ regions }}', 'fr')
            ->setCode(ValidPhoneNumber::REGION_ERROR)
            ->assertRaised();
    }

    public function testBothTypeAndCountryAreChecked(): void
    {
        $this->validate('+33 1 23 45 67 89', new ValidPhoneNumber(types: [PhoneNumberType::Mobile], regions: ['CA'], regionMessage: 'Not from {{ regions }}.'));

        $this->buildViolation('This phone number is not of an accepted type.')
            ->setCode(ValidPhoneNumber::TYPE_ERROR)
            ->buildNextViolation('Not from {{ regions }}.')
            ->setParameter('{{ regions }}', 'CA')
            ->setCode(ValidPhoneNumber::REGION_ERROR)
            ->assertRaised();
    }

    public function testTheConstraintHasItsDefaults(): void
    {
        $constraint = new ValidPhoneNumber(groups: ['contact']);
        $custom = new ValidPhoneNumber(message: 'Bad.');

        self::assertSame([null, null, null, 'This value is not a valid phone number.', 'This phone number is not of an accepted type.', 'This phone number is not from an accepted country: {{ regions }}.', ['contact'], 'Bad.'], [$constraint->region, $constraint->types, $constraint->regions, $constraint->message, $constraint->typeMessage, $constraint->regionMessage, $constraint->groups, $custom->message]);
        self::assertSame(['INVALID_ERROR', 'TYPE_ERROR', 'REGION_ERROR'], [ValidPhoneNumber::getErrorName(ValidPhoneNumber::INVALID_ERROR), ValidPhoneNumber::getErrorName(ValidPhoneNumber::TYPE_ERROR), ValidPhoneNumber::getErrorName(ValidPhoneNumber::REGION_ERROR)]);
    }

    public function testOnlyItsConstraintAndValuesAreTaken(): void
    {
        try {
            $this->validate('+15145550123', new NotBlank());
            self::fail('Another constraint was taken.');
        } catch (UnexpectedTypeException) {
        }

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('Expected argument of type "string|Phil\\Phone\\PhoneNumber", "stdClass" given');

        $this->validate(new stdClass(), new ValidPhoneNumber());
    }
}
