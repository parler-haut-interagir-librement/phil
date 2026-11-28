<?php

declare(strict_types=1);

namespace Phil\MeasurementBundle\Infrastructure\Validator\Attribute;

use Attribute;
use Override;
use Phil\MeasurementBundle\Infrastructure\Validator\MeasurementRangeValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * The `Measurement` lies between `min` and `max`, measurements written as `Measurement::toString()` writes them and
 * compared in any unit of their family: `max: '30 kg'` refuses 70 pounds. Either bound may be left out; both are
 * inclusive. A measurement of another family than the bounds is refused too. `null` is valid, as for every constraint
 * but `NotNull`.
 *
 * ```php
 * #[MeasurementRange(min: '0 kg', max: '30 kg')]
 * public ?Measurement $weight = null;
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class MeasurementRange extends Constraint
{
    public const string TOO_LOW_ERROR = '3f1c9e2a-6b4d-4a8e-9c7f-2d5e8b1a0c46';

    public const string TOO_HIGH_ERROR = '8d2b4f6a-1e3c-4b7d-a9e5-6c0f2a8d4b13';

    public const string FAMILY_ERROR = 'b7e1a3c5-9d2f-4e6b-8a4c-0f3d5b7e9a21';

    protected const array ERROR_NAMES = [
        self::TOO_LOW_ERROR => 'TOO_LOW_ERROR',
        self::TOO_HIGH_ERROR => 'TOO_HIGH_ERROR',
        self::FAMILY_ERROR => 'FAMILY_ERROR',
    ];

    public string $minMessage = 'This measurement should be {{ limit }} or more.';

    public string $maxMessage = 'This measurement should be {{ limit }} or less.';

    public string $familyMessage = 'This measurement cannot be compared with {{ limit }}.';

    /**
     * @param list<string>|null $groups the validation groups
     */
    public function __construct(
        public readonly ?string $min = null,
        public readonly ?string $max = null,
        ?string $minMessage = null,
        ?string $maxMessage = null,
        ?string $familyMessage = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        if (null === $min && null === $max) {
            throw new ConstraintDefinitionException('A measurement range needs a "min", a "max" or both.');
        }

        parent::__construct(null, $groups, $payload);

        $this->minMessage = $minMessage ?? $this->minMessage;
        $this->maxMessage = $maxMessage ?? $this->maxMessage;
        $this->familyMessage = $familyMessage ?? $this->familyMessage;
    }

    #[Override]
    public function validatedBy(): string
    {
        return MeasurementRangeValidator::class;
    }
}
