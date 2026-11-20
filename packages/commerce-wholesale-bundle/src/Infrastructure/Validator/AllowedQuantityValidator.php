<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Infrastructure\Validator;

use Override;
use Phil\CommerceWholesaleBundle\Application\QuantityRules;
use Phil\CommerceWholesaleBundle\Domain\Model\QuantityRule;
use Phil\CommerceWholesaleBundle\Domain\Quantity\QuantityBreach;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Checks an `AllowedQuantity` against the rule the SKU follows for the group, read once; the violation names the
 * minimum, the maximum, the increment and the closest quantity allowed, for the message to show.
 */
final class AllowedQuantityValidator extends ConstraintValidator
{
    public function __construct(
        private readonly QuantityRules $rules,
    ) {
    }

    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof AllowedQuantity) {
            throw new UnexpectedTypeException($constraint, AllowedQuantity::class);
        }

        if (null === $value) {
            return;
        }

        if (!$value instanceof RequestedQuantity) {
            throw new UnexpectedValueException($value, RequestedQuantity::class);
        }

        $rule = $this->rules->ruleFor($value->sku, $value->customerGroup);
        $breach = $rule?->breachOf($value->quantity);

        if (!$rule instanceof QuantityRule || !$breach instanceof QuantityBreach) {
            return;
        }

        [$message, $code] = match ($breach) {
            QuantityBreach::BelowMinimum => [$constraint->belowMinimumMessage, AllowedQuantity::BELOW_MINIMUM_ERROR],
            QuantityBreach::AboveMaximum => [$constraint->aboveMaximumMessage, AllowedQuantity::ABOVE_MAXIMUM_ERROR],
            QuantityBreach::OffIncrement => [$constraint->offIncrementMessage, AllowedQuantity::OFF_INCREMENT_ERROR],
        };

        $this->context->buildViolation($message)
            ->setParameter('{{ sku }}', $value->sku)
            ->setParameter('{{ quantity }}', (string) $value->quantity)
            ->setParameter('{{ minimum }}', (string) $rule->minimum())
            ->setParameter('{{ maximum }}', (string) $rule->maximum())
            ->setParameter('{{ increment }}', (string) $rule->increment())
            ->setParameter('{{ allowed }}', (string) $rule->nextAllowed($value->quantity))
            ->setInvalidValue($value->quantity)
            ->setCode($code)
            ->addViolation();
    }
}
