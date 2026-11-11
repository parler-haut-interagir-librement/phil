<?php

declare(strict_types=1);

namespace Phil\AddressBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\Address\Address;
use Phil\AddressBundle\Infrastructure\Validator\ValidAddress;
use Phil\AddressBundle\Infrastructure\Validator\ValidAddressValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<ValidAddressValidator>
 */
#[CoversClass(ValidAddress::class)]
#[CoversClass(ValidAddressValidator::class)]
final class ValidAddressValidatorTest extends ConstraintValidatorTestCase
{
    #[Override]
    protected function createValidator(): ValidAddressValidator
    {
        return new ValidAddressValidator();
    }

    public function testAValidAddressAndNothingAreValid(): void
    {
        $this->validate(null, new ValidAddress());
        $this->validate(Address::of('FR', ['addressLine1' => '10 rue de Rivoli', 'locality' => 'Paris', 'postalCode' => '75001']), new ValidAddress());

        $this->assertNoViolation();
    }

    public function testEachViolationIsOnItsField(): void
    {
        $this->validate(Address::of('CA', ['addressLine1' => '1', 'locality' => 'Montréal', 'administrativeArea' => 'Atlantis', 'postalCode' => '1', 'sortingCode' => 'X']), new ValidAddress());

        $this->buildViolation('This value is not part of an address of this country.')
            ->atPath('property.path.sortingCode')
            ->setCode(ValidAddress::UNUSED_ERROR)
            ->buildNextViolation('This postal code is not one of this country or region.')
            ->atPath('property.path.postalCode')
            ->setCode(ValidAddress::INVALID_POSTAL_CODE_ERROR)
            ->buildNextViolation('This place is not one of this country.')
            ->atPath('property.path.administrativeArea')
            ->setCode(ValidAddress::UNKNOWN_SUBDIVISION_ERROR)
            ->assertRaised();
    }

    public function testAMissingFieldIsSaid(): void
    {
        $this->validate(Address::of('FR', ['addressLine1' => '10 rue de Rivoli', 'locality' => 'Paris']), new ValidAddress());

        $this->buildViolation('This value is required in an address of this country.')
            ->atPath('property.path.postalCode')
            ->setCode(ValidAddress::MISSING_ERROR)
            ->assertRaised();
    }

    public function testTheConstraintNamesItsErrors(): void
    {
        self::assertSame(['MISSING_ERROR', 'UNUSED_ERROR', 'INVALID_POSTAL_CODE_ERROR', 'UNKNOWN_SUBDIVISION_ERROR'], array_map(ValidAddress::getErrorName(...), array_values(ValidAddress::CODES)));
        self::assertSame(['address'], new ValidAddress(['address'])->groups);
    }

    public function testOnlyItsConstraintAndAddressesAreTaken(): void
    {
        try {
            $this->validate(null, new NotBlank());
            self::fail('Another constraint was taken.');
        } catch (UnexpectedTypeException) {
        }

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('Expected argument of type "Phil\\Address\\Address", "stdClass" given');

        $this->validate(new stdClass(), new ValidAddress());
    }
}
