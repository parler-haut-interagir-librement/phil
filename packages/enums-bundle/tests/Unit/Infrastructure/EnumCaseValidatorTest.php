<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\EnumsBundle\Infrastructure\Validator\EnumCase;
use Phil\EnumsBundle\Infrastructure\Validator\EnumCaseValidator;
use Phil\EnumsBundle\Tests\Unit\Fixture\Level;
use Phil\EnumsBundle\Tests\Unit\Fixture\OrderStatus;
use Phil\EnumsBundle\Tests\Unit\Fixture\Priority;
use Phil\EnumsBundle\Tests\Unit\Fixture\Shape;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use UnitEnum;

/**
 * @extends ConstraintValidatorTestCase<EnumCaseValidator>
 */
#[CoversClass(EnumCase::class)]
#[CoversClass(EnumCaseValidator::class)]
final class EnumCaseValidatorTest extends ConstraintValidatorTestCase
{
    public function testNullIsValid(): void
    {
        $this->validate(null, new EnumCase(OrderStatus::class, inGroup: 'customer'));

        $this->assertNoViolation();
    }

    public function testACaseOfTheSelectionIsValid(): void
    {
        $this->validate(OrderStatus::IN_TRANSIT, new EnumCase(OrderStatus::class, inGroup: 'customer'));

        $this->assertNoViolation();
    }

    public function testABackedValueIsReadAsItsCase(): void
    {
        $this->validate('in_transit', new EnumCase(OrderStatus::class, inGroup: ['customer']));

        $this->assertNoViolation();
    }

    public function testANameIsReadAsTheCaseOfAPureEnum(): void
    {
        $this->validate('Circle', new EnumCase(Shape::class, only: [Shape::Circle]));

        $this->assertNoViolation();
    }

    public function testACaseOutsideTheSelectionIsRefused(): void
    {
        $this->validate(OrderStatus::CANCELLED, new EnumCase(OrderStatus::class, inGroup: 'customer'));

        $this->buildViolation('The value {{ value }} is not an allowed choice.')
            ->setParameter('{{ value }}', '"CANCELLED"')
            ->setCode(EnumCase::NOT_ALLOWED_ERROR)
            ->assertRaised();
    }

    public function testEveryCriterionOfTheSelectionApplies(): void
    {
        $constraint = new EnumCase(OrderStatus::class, only: [OrderStatus::PENDING, OrderStatus::IN_TRANSIT], except: [OrderStatus::PENDING], whereMeta: [Priority::class => 2], message: 'Not this one.');

        $this->validate(OrderStatus::PENDING, $constraint);

        $this->buildViolation('Not this one.')
            ->setParameter('{{ value }}', '"PENDING"')
            ->setCode(EnumCase::NOT_ALLOWED_ERROR)
            ->assertRaised();
    }

    public function testAValueThatIsNoCaseIsRefused(): void
    {
        $this->validate('shipped', new EnumCase(OrderStatus::class));

        $this->buildViolation('The value {{ value }} is not an allowed choice.')
            ->setParameter('{{ value }}', '"shipped"')
            ->setCode(EnumCase::NOT_A_CASE_ERROR)
            ->assertRaised();
    }

    public function testACaseOfAnotherEnumIsNoCase(): void
    {
        $this->validate(Shape::Circle, new EnumCase(OrderStatus::class));

        $this->buildViolation('The value {{ value }} is not an allowed choice.')
            ->setParameter('{{ value }}', 'Circle')
            ->setCode(EnumCase::NOT_A_CASE_ERROR)
            ->assertRaised();
    }

    public function testANonScalarValueIsNoCase(): void
    {
        $this->validate(['in_transit'], new EnumCase(OrderStatus::class));

        $this->buildViolation('The value {{ value }} is not an allowed choice.')
            ->setParameter('{{ value }}', 'array')
            ->setCode(EnumCase::NOT_A_CASE_ERROR)
            ->assertRaised();
    }

    public function testAnIntegerIsNoNameOfAPureEnum(): void
    {
        $this->validate(1, new EnumCase(Shape::class));

        $this->buildViolation('The value {{ value }} is not an allowed choice.')
            ->setParameter('{{ value }}', '1')
            ->setCode(EnumCase::NOT_A_CASE_ERROR)
            ->assertRaised();
    }

    public function testAnIntegerWrittenAsTextIsReadAsTheCaseOfAnEnumBackedByIntegers(): void
    {
        $this->validate('2', new EnumCase(Level::class));
        $this->validate(1, new EnumCase(Level::class));

        $this->assertNoViolation();
    }

    /**
     * @return iterable<string, array{int|string, class-string<UnitEnum>, string}>
     */
    public static function valuesOfTheWrongType(): iterable
    {
        yield 'text for integers' => ['abc', Level::class, '"abc"'];
        yield 'a decimal for integers' => ['1.0', Level::class, '"1.0"'];
        yield 'a leading zero for integers' => ['01', Level::class, '"01"'];
        yield 'a sign for integers' => ['+1', Level::class, '"+1"'];
        yield 'spaces for integers' => [' 1', Level::class, '" 1"'];
        yield 'an empty text for integers' => ['', Level::class, '""'];
        yield 'beyond the integers' => ['99999999999999999999', Level::class, '"99999999999999999999"'];
        yield 'an integer for strings' => [1, OrderStatus::class, '1'];
    }

    /**
     * @param class-string<UnitEnum> $enum
     */
    #[DataProvider('valuesOfTheWrongType')]
    public function testAValueOfTheWrongTypeIsNoCase(int|string $value, string $enum, string $shown): void
    {
        $this->validate($value, new EnumCase($enum));

        $this->buildViolation('The value {{ value }} is not an allowed choice.')
            ->setParameter('{{ value }}', $shown)
            ->setCode(EnumCase::NOT_A_CASE_ERROR)
            ->assertRaised();
    }

    public function testAnotherConstraintIsRefused(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validate('x', new NotNull());
    }

    public function testTheConstraintKeepsItsValidationGroups(): void
    {
        self::assertSame(['strict'], new EnumCase(OrderStatus::class, groups: ['strict'])->groups);
        self::assertSame('NOT_ALLOWED_ERROR', EnumCase::getErrorName(EnumCase::NOT_ALLOWED_ERROR));
        self::assertSame('NOT_A_CASE_ERROR', EnumCase::getErrorName(EnumCase::NOT_A_CASE_ERROR));
    }

    #[Override]
    protected function createValidator(): EnumCaseValidator
    {
        return new EnumCaseValidator();
    }
}
