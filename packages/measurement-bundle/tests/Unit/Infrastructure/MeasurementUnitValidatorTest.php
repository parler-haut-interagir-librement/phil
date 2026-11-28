<?php

declare(strict_types=1);

namespace Phil\MeasurementBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\Measurement\UnitRegistry;
use Phil\MeasurementBundle\Infrastructure\Validator\Attribute\MeasurementUnit;
use Phil\MeasurementBundle\Infrastructure\Validator\MeasurementUnitValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<MeasurementUnitValidator>
 */
#[CoversClass(MeasurementUnitValidator::class)]
#[CoversClass(MeasurementUnit::class)]
final class MeasurementUnitValidatorTest extends ConstraintValidatorTestCase
{
    #[Override]
    protected function createValidator(): MeasurementUnitValidator
    {
        return new MeasurementUnitValidator();
    }

    public function testAMeasurementOfTheFamilyAndAnAcceptedUnitIsValid(): void
    {
        $registry = UnitRegistry::standard();

        $this->validate($registry->measurement('1', 'kg'), new MeasurementUnit(family: 'mass'));
        $this->validate($registry->measurement('1', 'g'), new MeasurementUnit(family: 'mass', units: ['g', 'kg']));
        $this->validate($registry->measurement('1', 'g'), new MeasurementUnit(units: ['g']));
        $this->validate(null, new MeasurementUnit(family: 'mass'));

        $this->assertNoViolation();
    }

    public function testTheGroupsAndThePayloadAreKept(): void
    {
        $constraint = new MeasurementUnit(family: 'mass', groups: ['edit'], payload: ['severity' => 'low']);

        self::assertSame(['edit'], $constraint->groups);
        self::assertSame(['severity' => 'low'], $constraint->payload);
    }

    public function testAMeasurementOfAnotherFamilyIsRefused(): void
    {
        $this->validate(UnitRegistry::standard()->measurement('1', 'm'), new MeasurementUnit(family: 'mass', units: ['g']));

        $this->buildViolation('This value should be a measurement of {{ family }}.')
            ->setParameter('{{ family }}', 'mass')
            ->setCode(MeasurementUnit::FAMILY_ERROR)
            ->assertRaised();
    }

    public function testAUnitOutsideTheListIsRefused(): void
    {
        $this->validate(UnitRegistry::standard()->measurement('1', '[lb_av]'), new MeasurementUnit(family: 'mass', units: ['g', 'kg'], unitMessage: 'No {{ unit }}.'));

        $this->buildViolation('No {{ unit }}.')
            ->setParameter('{{ unit }}', '[lb_av]')
            ->setCode(MeasurementUnit::UNIT_ERROR)
            ->assertRaised();
    }

    public function testCustomMessagesAreKept(): void
    {
        $constraint = new MeasurementUnit(family: 'mass', familyMessage: 'family');

        self::assertSame(['family', 'The unit {{ unit }} is not accepted.'], [$constraint->familyMessage, $constraint->unitMessage]);
        self::assertSame(MeasurementUnitValidator::class, $constraint->validatedBy());
        self::assertSame('UNIT_ERROR', MeasurementUnit::getErrorName(MeasurementUnit::UNIT_ERROR));
    }

    public function testTheConstraintNamesAFamilyOrUnits(): void
    {
        $this->expectException(ConstraintDefinitionException::class);
        new MeasurementUnit();
    }

    public function testAValueThatIsNotAMeasurementIsRefused(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->validate(new stdClass(), new MeasurementUnit(family: 'mass'));
    }

    public function testAnotherConstraintIsRefused(): void
    {
        $this->expectException(UnexpectedTypeException::class);
        $this->validate(null, new NotNull());
    }
}
