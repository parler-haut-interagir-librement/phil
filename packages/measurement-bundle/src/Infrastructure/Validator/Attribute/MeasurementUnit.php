<?php

declare(strict_types=1);

namespace Phil\MeasurementBundle\Infrastructure\Validator\Attribute;

use Attribute;
use Override;
use Phil\MeasurementBundle\Infrastructure\Validator\MeasurementUnitValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * The `Measurement` is of the `family` given, in one of the `units` given when there are some: a product's weight is a
 * mass, a parcel's in grams or kilograms. `null` is valid, as for every constraint but `NotNull`.
 *
 * ```php
 * #[MeasurementUnit(family: 'mass', units: ['g', 'kg'])]
 * public ?Measurement $weight = null;
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class MeasurementUnit extends Constraint
{
    public const string FAMILY_ERROR = '4a6c8e0b-2d4f-4b8a-9c1e-3f5a7c9e1b62';

    public const string UNIT_ERROR = 'e9b1d3f5-7a2c-4e6b-8d0f-1a3c5e7b9d24';

    protected const array ERROR_NAMES = [
        self::FAMILY_ERROR => 'FAMILY_ERROR',
        self::UNIT_ERROR => 'UNIT_ERROR',
    ];

    public string $familyMessage = 'This value should be a measurement of {{ family }}.';

    public string $unitMessage = 'The unit {{ unit }} is not accepted.';

    /**
     * @param list<string>      $units  the codes of the units accepted; every unit of the family when empty
     * @param list<string>|null $groups the validation groups
     */
    public function __construct(
        public readonly ?string $family = null,
        public readonly array $units = [],
        ?string $familyMessage = null,
        ?string $unitMessage = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        if (null === $family && [] === $units) {
            throw new ConstraintDefinitionException('A measurement unit constraint names a "family", "units" or both.');
        }

        parent::__construct(null, $groups, $payload);

        $this->familyMessage = $familyMessage ?? $this->familyMessage;
        $this->unitMessage = $unitMessage ?? $this->unitMessage;
    }

    #[Override]
    public function validatedBy(): string
    {
        return MeasurementUnitValidator::class;
    }
}
