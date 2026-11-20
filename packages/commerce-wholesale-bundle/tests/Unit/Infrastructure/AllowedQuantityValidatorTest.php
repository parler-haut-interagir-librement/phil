<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\CommerceWholesaleBundle\Application\QuantityRules;
use Phil\CommerceWholesaleBundle\Domain\Model\QuantityRule;
use Phil\CommerceWholesaleBundle\Infrastructure\Validator\AllowedQuantity;
use Phil\CommerceWholesaleBundle\Infrastructure\Validator\AllowedQuantityValidator;
use Phil\CommerceWholesaleBundle\Infrastructure\Validator\RequestedQuantity;
use Phil\CommerceWholesaleBundle\Tests\Unit\Support\InMemoryQuantityRules;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The constraint checked by Symfony's validator, as an application's form or the guard asks it.
 */
#[CoversClass(AllowedQuantity::class)]
#[CoversClass(AllowedQuantityValidator::class)]
#[CoversClass(RequestedQuantity::class)]
final class AllowedQuantityValidatorTest extends TestCase
{
    private ValidatorInterface $validator;

    #[Override]
    protected function setUp(): void
    {
        $this->validator = $this->validatorWith(new AllowedQuantityValidator(new QuantityRules(new InMemoryQuantityRules(
            QuantityRule::of('DICE', 'wholesale', 6, 60, 6),
            QuantityRule::of('DICE', null, 1, 10),
        ))), AllowedQuantityValidator::class);
    }

    public function testAnAllowedQuantityPasses(): void
    {
        self::assertSame([], $this->violations(new RequestedQuantity('DICE', 12, 'wholesale')));
        self::assertSame([], $this->violations(new RequestedQuantity('DICE', 3)));
        self::assertSame([], $this->violations(new RequestedQuantity('DICE', 10, 'retail')));
        self::assertSame([], $this->violations(new RequestedQuantity('CARDS', 7, 'wholesale')));
        self::assertSame([], $this->violations(null));
    }

    public function testUnderTheMinimum(): void
    {
        self::assertSame([[
            'phil_commerce_wholesale.quantity.below_minimum',
            ['{{ sku }}' => 'DICE', '{{ quantity }}' => '5', '{{ minimum }}' => '6', '{{ maximum }}' => '60', '{{ increment }}' => '6', '{{ allowed }}' => '6'],
            5,
            AllowedQuantity::BELOW_MINIMUM_ERROR,
        ]], $this->violations(new RequestedQuantity('DICE', 5, 'wholesale')));
    }

    public function testOverTheMaximum(): void
    {
        self::assertSame([[
            'Too many.',
            ['{{ sku }}' => 'DICE', '{{ quantity }}' => '11', '{{ minimum }}' => '1', '{{ maximum }}' => '10', '{{ increment }}' => '1', '{{ allowed }}' => ''],
            11,
            AllowedQuantity::ABOVE_MAXIMUM_ERROR,
        ]], $this->violations(new RequestedQuantity('DICE', 11), new AllowedQuantity(aboveMaximumMessage: 'Too many.')));
    }

    public function testBetweenTwoSteps(): void
    {
        self::assertSame([[
            'Off.',
            ['{{ sku }}' => 'DICE', '{{ quantity }}' => '13', '{{ minimum }}' => '6', '{{ maximum }}' => '60', '{{ increment }}' => '6', '{{ allowed }}' => '18'],
            13,
            AllowedQuantity::OFF_INCREMENT_ERROR,
        ]], $this->violations(new RequestedQuantity('DICE', 13, 'wholesale'), new AllowedQuantity('Below.', 'Above.', 'Off.')));
    }

    public function testTheConstraintNamesItsErrorsAndItsValidator(): void
    {
        $constraint = new AllowedQuantity(groups: ['cart'], payload: 'p');
        $custom = new AllowedQuantity('Below.', 'Above.', 'Off.');

        self::assertSame(AllowedQuantityValidator::class, $constraint->validatedBy());
        self::assertSame(['cart'], $constraint->groups);
        self::assertSame('p', $constraint->payload);
        self::assertSame('BELOW_MINIMUM_ERROR', AllowedQuantity::getErrorName(AllowedQuantity::BELOW_MINIMUM_ERROR));
        self::assertSame('ABOVE_MAXIMUM_ERROR', AllowedQuantity::getErrorName(AllowedQuantity::ABOVE_MAXIMUM_ERROR));
        self::assertSame('OFF_INCREMENT_ERROR', AllowedQuantity::getErrorName(AllowedQuantity::OFF_INCREMENT_ERROR));
        self::assertSame(['phil_commerce_wholesale.quantity.below_minimum', 'phil_commerce_wholesale.quantity.above_maximum', 'phil_commerce_wholesale.quantity.off_increment'], [$constraint->belowMinimumMessage, $constraint->aboveMaximumMessage, $constraint->offIncrementMessage]);
        self::assertSame(['Below.', 'Above.', 'Off.'], [$custom->belowMinimumMessage, $custom->aboveMaximumMessage, $custom->offIncrementMessage]);
    }

    public function testAnotherConstraintIsRefused(): void
    {
        $validator = $this->validatorWith(new AllowedQuantityValidator(new QuantityRules(new InMemoryQuantityRules())), NotNull::class.'Validator');

        $this->expectException(UnexpectedTypeException::class);

        $validator->validate(new RequestedQuantity('DICE', 1), new NotNull());
    }

    /**
     * The validator turns the validator's `UnexpectedValueException` into a violation of type.
     */
    public function testAnotherValueIsRefused(): void
    {
        $violations = $this->validator->validate(new stdClass(), new AllowedQuantity());

        self::assertCount(1, $violations);
        self::assertSame('This value should be of type {{ type }}.', $violations->get(0)->getMessageTemplate());
        self::assertSame(['{{ type }}' => RequestedQuantity::class], $violations->get(0)->getParameters());
    }

    private function validatorWith(AllowedQuantityValidator $validator, string $as): ValidatorInterface
    {
        return Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([$as => $validator]))
            ->getValidator();
    }

    /**
     * @return list<array{string, array<array-key, mixed>, mixed, ?string}>
     */
    private function violations(?RequestedQuantity $requested, ?Constraint $constraint = null): array
    {
        $violations = [];

        foreach ($this->validator->validate($requested, $constraint ?? new AllowedQuantity()) as $violation) {
            self::assertInstanceOf(ConstraintViolationInterface::class, $violation);
            $violations[] = [$violation->getMessageTemplate(), $violation->getParameters(), $violation->getInvalidValue(), $violation->getCode()];
        }

        return $violations;
    }
}
