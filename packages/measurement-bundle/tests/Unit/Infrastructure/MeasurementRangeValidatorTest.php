<?php

declare(strict_types=1);

namespace Phil\MeasurementBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\Measurement\Exception\MeasurementException;
use Phil\Measurement\UnitRegistry;
use Phil\MeasurementBundle\Infrastructure\Validator\Attribute\MeasurementRange;
use Phil\MeasurementBundle\Infrastructure\Validator\MeasurementRangeValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<MeasurementRangeValidator>
 */
#[CoversClass(MeasurementRangeValidator::class)]
#[CoversClass(MeasurementRange::class)]
final class MeasurementRangeValidatorTest extends ConstraintValidatorTestCase
{
    #[Override]
    protected function createValidator(): MeasurementRangeValidator
    {
        return new MeasurementRangeValidator(UnitRegistry::standard());
    }

    public function testTheGroupsAndThePayloadAreKept(): void
    {
        $constraint = new MeasurementRange(min: '1 kg', groups: ['edit'], payload: ['severity' => 'low']);

        self::assertSame(['edit'], $constraint->groups);
        self::assertSame(['severity' => 'low'], $constraint->payload);
    }

    public function testABoundThatIsNotAMeasurementKeepsTheLibrarysRefusalAsThePrevious(): void
    {
        try {
            $this->validate(UnitRegistry::standard()->measurement('1', 'kg'), new MeasurementRange(min: 'heavy'));
            self::fail('A bound is a measurement.');
        } catch (ConstraintDefinitionException $constraintDefinitionException) {
            self::assertInstanceOf(MeasurementException::class, $constraintDefinitionException->getPrevious());
            self::assertSame(0, $constraintDefinitionException->getCode());
        }
    }

    public function testAMeasurementWithinTheRangeIsValid(): void
    {
        $registry = UnitRegistry::standard();

        $this->validate($registry->measurement('66', '[lb_av]'), new MeasurementRange(min: '0 kg', max: '30 kg'));
        $this->validate($registry->measurement('30', 'kg'), new MeasurementRange(max: '30 kg'));
        $this->validate($registry->measurement('0', 'g'), new MeasurementRange(min: '0 kg'));
        $this->validate(null, new MeasurementRange(min: '0 kg'));

        $this->assertNoViolation();
    }

    public function testAMeasurementAboveTheMaximumIsRefused(): void
    {
        $this->validate(UnitRegistry::standard()->measurement('70', '[lb_av]'), new MeasurementRange(min: '0 kg', max: '30 kg'));

        $this->buildViolation('This measurement should be {{ limit }} or less.')
            ->setParameter('{{ limit }}', '30 kg')
            ->setCode(MeasurementRange::TOO_HIGH_ERROR)
            ->assertRaised();
    }

    public function testAMeasurementBelowTheMinimumIsRefused(): void
    {
        $this->validate(UnitRegistry::standard()->measurement('-1', 'g'), new MeasurementRange(min: '0 kg', minMessage: 'Too light: {{ limit }}.'));

        $this->buildViolation('Too light: {{ limit }}.')
            ->setParameter('{{ limit }}', '0 kg')
            ->setCode(MeasurementRange::TOO_LOW_ERROR)
            ->assertRaised();
    }

    public function testAMeasurementOfAnotherFamilyIsRefusedOnce(): void
    {
        $this->validate(UnitRegistry::standard()->measurement('1', 'm'), new MeasurementRange(min: '0 kg', max: '30 kg', familyMessage: 'Not a mass: {{ limit }}.'));

        $this->buildViolation('Not a mass: {{ limit }}.')
            ->setParameter('{{ limit }}', '0 kg')
            ->setCode(MeasurementRange::FAMILY_ERROR)
            ->assertRaised();
    }

    public function testCustomMessagesAreKept(): void
    {
        $range = new MeasurementRange(min: '1 kg', maxMessage: 'max', familyMessage: 'family');

        self::assertSame(['This measurement should be {{ limit }} or more.', 'max', 'family'], [$range->minMessage, $range->maxMessage, $range->familyMessage]);
        self::assertSame(MeasurementRangeValidator::class, $range->validatedBy());
        self::assertSame('TOO_HIGH_ERROR', MeasurementRange::getErrorName(MeasurementRange::TOO_HIGH_ERROR));
    }

    public function testARangeNeedsABound(): void
    {
        $this->expectException(ConstraintDefinitionException::class);
        new MeasurementRange();
    }

    public function testAnUnreadableBoundIsAMistakeOfTheConstraint(): void
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessageIsOrContains('The bound "30" of a measurement range is not a measurement');
        $this->validate(UnitRegistry::standard()->measurement('1', 'kg'), new MeasurementRange(max: '30'));
    }

    public function testAValueThatIsNotAMeasurementIsRefused(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->validate(new stdClass(), new MeasurementRange(max: '30 kg'));
    }

    public function testAnotherConstraintIsRefused(): void
    {
        $this->expectException(UnexpectedTypeException::class);
        $this->validate(null, new NotNull());
    }
}
