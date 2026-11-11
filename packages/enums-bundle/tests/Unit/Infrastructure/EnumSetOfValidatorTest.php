<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Infrastructure;

use InvalidArgumentException;
use Override;
use Phil\Enums\Collection\EnumSet;
use Phil\EnumsBundle\Infrastructure\Validator\EnumCase;
use Phil\EnumsBundle\Infrastructure\Validator\EnumSetOf;
use Phil\EnumsBundle\Infrastructure\Validator\EnumSetOfValidator;
use Phil\EnumsBundle\Tests\Unit\Fixture\Access;
use Phil\EnumsBundle\Tests\Unit\Fixture\Shape;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<EnumSetOfValidator>
 */
#[CoversClass(EnumSetOf::class)]
#[CoversClass(EnumSetOfValidator::class)]
final class EnumSetOfValidatorTest extends ConstraintValidatorTestCase
{
    public function testNullIsValid(): void
    {
        $this->validate(null, new EnumSetOf(Access::class));

        $this->assertNoViolation();
    }

    public function testASetOfAllowedCasesWithinTheBoundsIsValid(): void
    {
        $this->validate(EnumSet::of(Access::class, Access::Read, Access::Write), new EnumSetOf(Access::class, inGroup: 'basic', min: 1, max: 2));

        $this->assertNoViolation();
    }

    public function testAnEmptySetWithoutBoundsIsValid(): void
    {
        $this->validate(EnumSet::none(Access::class), new EnumSetOf(Access::class));

        $this->assertNoViolation();
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function notASet(): iterable
    {
        yield 'a case' => [Access::Read, 'Read'];
        yield 'a list' => [[Access::Read], 'array'];
        yield 'a set of another enum' => [EnumSet::of(Shape::class, Shape::Circle), 'object'];
    }

    #[DataProvider('notASet')]
    public function testAValueThatIsNotASetOfTheEnumIsOneViolation(mixed $value, string $formatted): void
    {
        $this->validate($value, new EnumSetOf(Access::class, min: 3, message: 'not a set {{ value }}'));

        $this->buildViolation('not a set {{ value }}')
            ->setParameter('{{ value }}', $formatted)
            ->setCode(EnumSetOf::NOT_A_SET_ERROR)
            ->assertRaised();
    }

    public function testEachCaseOutsideTheSelectionIsAViolation(): void
    {
        $this->validate(EnumSet::all(Access::class), new EnumSetOf(Access::class, only: [Access::Read], notAllowedMessage: 'no {{ value }}'));

        $this->buildViolation('no {{ value }}')
            ->setParameter('{{ value }}', '"Write"')
            ->setCode(EnumSetOf::NOT_ALLOWED_ERROR)
            ->buildNextViolation('no {{ value }}')
            ->setParameter('{{ value }}', '"Admin"')
            ->setCode(EnumSetOf::NOT_ALLOWED_ERROR)
            ->assertRaised();
    }

    public function testTooFewCasesIsAViolation(): void
    {
        $this->validate(EnumSet::of(Access::class, Access::Read), new EnumSetOf(Access::class, min: 2, minMessage: 'few {{ count }}/{{ limit }}'));

        $this->buildViolation('few {{ count }}/{{ limit }}')
            ->setParameter('{{ count }}', '1')
            ->setParameter('{{ limit }}', '2')
            ->setPlural(2)
            ->setCode(EnumSetOf::TOO_FEW_ERROR)
            ->assertRaised();
    }

    public function testTooManyCasesIsAViolation(): void
    {
        $this->validate(EnumSet::all(Access::class), new EnumSetOf(Access::class, max: 2, maxMessage: 'many {{ count }}/{{ limit }}'));

        $this->buildViolation('many {{ count }}/{{ limit }}')
            ->setParameter('{{ count }}', '3')
            ->setParameter('{{ limit }}', '2')
            ->setPlural(2)
            ->setCode(EnumSetOf::TOO_MANY_ERROR)
            ->assertRaised();
    }

    public function testTheBoundsAreInclusive(): void
    {
        $this->validate(EnumSet::of(Access::class, Access::Read, Access::Write), new EnumSetOf(Access::class, min: 2, max: 2));

        $this->assertNoViolation();
    }

    public function testTheDefaultMessages(): void
    {
        $constraint = new EnumSetOf(Access::class);

        self::assertSame('This value is not a set of allowed choices.', $constraint->message);
        self::assertSame('The value {{ value }} is not an allowed choice.', $constraint->notAllowedMessage);
        self::assertStringStartsWith('You must select at least {{ limit }} choice.', $constraint->minMessage);
        self::assertStringStartsWith('You must select at most {{ limit }} choice.', $constraint->maxMessage);
        self::assertSame('TOO_MANY_ERROR', EnumSetOf::getErrorName(EnumSetOf::TOO_MANY_ERROR));
        self::assertSame(['basic'], new EnumSetOf(Access::class, inGroup: 'basic')->selection->groups);
        self::assertSame(['Default'], $constraint->groups);
        self::assertSame(['strict'], new EnumSetOf(Access::class, groups: ['strict'])->groups);
    }

    /**
     * @return iterable<string, array{int|null, int|null, string}>
     */
    public static function badBounds(): iterable
    {
        yield 'negative minimum' => [-1, null, '-1 and none given.'];
        yield 'negative maximum' => [null, -1, 'none and -1 given.'];
        yield 'minimum above maximum' => [3, 2, '3 and 2 given.'];
    }

    #[DataProvider('badBounds')]
    public function testBadBoundsAreRefused(?int $min, ?int $max, string $given): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The bounds of a set are 0 or more, the minimum at most the maximum; '.$given);

        new EnumSetOf(Access::class, min: $min, max: $max);
    }

    public function testAnotherConstraintIsRefused(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validate(EnumSet::none(Access::class), new EnumCase(Access::class));
    }

    #[Override]
    protected function createValidator(): ConstraintValidatorInterface
    {
        return new EnumSetOfValidator();
    }
}
