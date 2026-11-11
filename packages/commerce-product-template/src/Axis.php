<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate;

use Phil\CommerceProductTemplate\Exception\InvalidDefinition;
use Phil\CommerceProductTemplate\Internal\Check;

/**
 * What a product varies by — a size, a colour, a version — and its values, in the order a selector lists them.
 */
final readonly class Axis
{
    /** The most values an axis holds. */
    public const int MAX_VALUES = 500;

    /** Codes the SKU pattern gives a meaning of its own. */
    public const array RESERVED = ['design', 'lang', 'template'];

    /**
     * @param non-empty-array<string, AxisValue> $values by code, in order
     */
    private function __construct(
        public string $code,
        public Label $label,
        private array $values,
    ) {
    }

    /**
     * @param string $code up to 32 lowercase letters, digits or "_", starting with a letter; never `design`, `lang` nor
     *                     `template`
     */
    public static function of(string $code, Label $label, AxisValue ...$values): self
    {
        Check::identifier($code, 'an axis');

        if (\in_array($code, self::RESERVED, true)) {
            throw InvalidDefinition::because(\sprintf('"%s" names a part of the SKU of its own, not an axis.', $code));
        }

        if ([] === $values || \count($values) > self::MAX_VALUES) {
            throw InvalidDefinition::because(\sprintf('The axis %s holds 1 to %d values.', $code, self::MAX_VALUES));
        }

        $keyed = [];

        foreach ($values as $value) {
            if (isset($keyed[$value->code])) {
                throw InvalidDefinition::because(\sprintf('The axis %s lists the value %s twice.', $code, $value->code));
            }

            $keyed[$value->code] = $value;
        }

        return new self($code, $label, $keyed);
    }

    /**
     * @return non-empty-list<AxisValue> in order
     */
    public function values(): array
    {
        return array_values($this->values);
    }

    public function value(string $code): ?AxisValue
    {
        return $this->values[$code] ?? null;
    }

    public function has(string $code): bool
    {
        return isset($this->values[$code]);
    }
}
