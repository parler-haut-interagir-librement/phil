<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Infrastructure\Validator;

use Attribute;
use Override;
use Symfony\Component\Validator\Constraint;

/**
 * A `RequestedQuantity` its SKU's quantity rule allows for the buyer's customer group: at least the minimum, at most
 * the maximum, in steps of the increment. A SKU without a rule takes any quantity; `null` passes, as with every
 * constraint but `NotNull`.
 *
 * The messages are keys of the `validators` translation domain, in English and French.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class AllowedQuantity extends Constraint
{
    public const string BELOW_MINIMUM_ERROR = '4f3c1d2e-8a6b-4c7d-9e0f-1a2b3c4d5e61';

    public const string ABOVE_MAXIMUM_ERROR = '7b8a9c0d-1e2f-4a3b-8c4d-5e6f7a8b9c02';

    public const string OFF_INCREMENT_ERROR = 'a1b2c3d4-e5f6-4a7b-9c8d-0e1f2a3b4c53';

    protected const array ERROR_NAMES = [
        self::BELOW_MINIMUM_ERROR => 'BELOW_MINIMUM_ERROR',
        self::ABOVE_MAXIMUM_ERROR => 'ABOVE_MAXIMUM_ERROR',
        self::OFF_INCREMENT_ERROR => 'OFF_INCREMENT_ERROR',
    ];

    public string $belowMinimumMessage = 'phil_commerce_wholesale.quantity.below_minimum';

    public string $aboveMaximumMessage = 'phil_commerce_wholesale.quantity.above_maximum';

    public string $offIncrementMessage = 'phil_commerce_wholesale.quantity.off_increment';

    /**
     * @param list<string>|null $groups
     */
    public function __construct(
        ?string $belowMinimumMessage = null,
        ?string $aboveMaximumMessage = null,
        ?string $offIncrementMessage = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);
        $this->belowMinimumMessage = $belowMinimumMessage ?? $this->belowMinimumMessage;
        $this->aboveMaximumMessage = $aboveMaximumMessage ?? $this->aboveMaximumMessage;
        $this->offIncrementMessage = $offIncrementMessage ?? $this->offIncrementMessage;
    }

    #[Override]
    public function validatedBy(): string
    {
        return AllowedQuantityValidator::class;
    }
}
