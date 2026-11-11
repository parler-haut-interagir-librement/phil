<?php

declare(strict_types=1);

namespace Phil\MoneyBundle\Infrastructure\Validator;

use Attribute;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * The `Money` lies between `min` and `max`, decimals read in the money's own currency: `min: '0'` refuses a
 * negative price whatever its currency, `max: '500'` caps it at 500 units of it. Either bound may be left out;
 * both are inclusive. A bound finer than the currency counts is rounded towards the inside of the range: `min:
 * '0.01'` asks for 1 yen. `null` is valid, as for every constraint but `NotNull`.
 *
 * ```php
 * #[MoneyRange(min: '0.01')]
 * public ?Money $price = null;
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class MoneyRange extends Constraint
{
    public const string TOO_LOW_ERROR = 'c2e8a4d1-9b7f-4c3a-8e5d-1f6b0a9c7e23';

    public const string TOO_HIGH_ERROR = '7a1d5f3e-2c9b-4e6a-a8d4-5b0c3e9f1a76';

    protected const array ERROR_NAMES = [
        self::TOO_LOW_ERROR => 'TOO_LOW_ERROR',
        self::TOO_HIGH_ERROR => 'TOO_HIGH_ERROR',
    ];

    public string $minMessage = 'This amount should be {{ limit }} or more.';

    public string $maxMessage = 'This amount should be {{ limit }} or less.';

    /**
     * @param list<string>|null $groups the validation groups
     */
    public function __construct(
        public readonly ?string $min = null,
        public readonly ?string $max = null,
        ?string $minMessage = null,
        ?string $maxMessage = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        if (null === $min && null === $max) {
            throw new ConstraintDefinitionException('A money range needs a "min", a "max" or both.');
        }

        foreach ([$min, $max] as $bound) {
            if (null !== $bound && 1 !== preg_match('/^-?\d+(?:\.\d+)?$/D', $bound)) {
                throw new ConstraintDefinitionException(\sprintf('The bound "%s" of a money range is not a decimal.', $bound));
            }
        }

        parent::__construct(null, $groups, $payload);

        $this->minMessage = $minMessage ?? $this->minMessage;
        $this->maxMessage = $maxMessage ?? $this->maxMessage;
    }
}
