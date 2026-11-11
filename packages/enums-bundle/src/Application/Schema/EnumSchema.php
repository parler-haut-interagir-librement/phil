<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Application\Schema;

use BackedEnum;
use Phil\Enums\Collection\CaseCollection;
use Phil\Enums\Meta\Reflection;
use Phil\EnumsBundle\Application\Translation\EnumTranslation;
use ReflectionEnum;
use UnitEnum;

/**
 * The JSON Schema of an enum, as an OpenAPI document describes a property: its type, its values, and what a
 * generator of clients reads beside them.
 *
 * ```php
 * EnumSchema::of(OrderStatus::class);
 * // ['type' => 'string', 'enum' => ['pending', 'in_transit'], 'x-enum-varnames' => ['PENDING', 'IN_TRANSIT'],
 * //  'x-enum-descriptions' => ['Waiting for payment.', 'In transit'], 'default' => 'pending']
 * ```
 *
 * - **`type`** is `integer` for an enum backed by integers, `string` otherwise; a pure enum is written by its names.
 * - **`x-enum-varnames`** and **`x-enum-descriptions`** are the extensions client generators read for the names of
 *   the constants and their comments: the case names, and each case's `Description`, or its `Label`, or its name.
 * - **`default`** is the case marked `#[DefaultCase]`, when there is one among the values.
 * - **Deprecated cases are left out**, unless asked for: a contract offers what clients may send.
 */
final class EnumSchema
{
    private function __construct()
    {
    }

    /**
     * @param class-string<UnitEnum> $enum
     *
     * @return array{type: 'integer'|'string', enum: list<int|string>, x-enum-varnames: list<string>, x-enum-descriptions: list<string>, default?: int|string}
     */
    public static function of(string $enum, bool $withDeprecated = false): array
    {
        $cases = CaseCollection::of($enum::cases());
        $cases = $withDeprecated ? $cases : $cases->withoutDeprecated();

        $schema = [
            'type' => 'int' === (string) new ReflectionEnum($enum)->getBackingType() ? 'integer' : 'string',
            'enum' => $cases->values(),
            'x-enum-varnames' => $cases->names(),
            'x-enum-descriptions' => $cases->map(static fn (UnitEnum $case): string => EnumTranslation::descriptionKey($case) ?? EnumTranslation::plainLabel($case)),
        ];

        $default = Reflection::defaultCase($enum);

        if ($default instanceof UnitEnum && $cases->contains($default)) {
            $schema['default'] = $default instanceof BackedEnum ? $default->value : $default->name;
        }

        return $schema;
    }
}
