<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The option values of an order line, by option code: a text, a choice, an ink, a draw. Checked by the sellable's
 * `OptionSet` when the line is added, then kept with the order as it was sold.
 *
 * Its **fingerprint** tells configurations apart: two lines of the same sellable are one line when their chosen values
 * are equal, two lines otherwise. Draws are left out of it, so a line keeps its reference once drawn.
 */
final readonly class LineConfiguration
{
    /** The version of the stored form of a configuration, written with each configured line. */
    public const int VERSION = 1;

    /**
     * @param array<string, OptionValue> $values sorted by code
     */
    private function __construct(
        public array $values,
    ) {
    }

    public static function none(): self
    {
        return new self([]);
    }

    /**
     * @param array<string, OptionValue> $values by option code, up to 32 lowercase letters, digits or "_"
     */
    public static function of(array $values): self
    {
        foreach (array_keys($values) as $code) {
            self::checkCode($code);
        }

        ksort($values, \SORT_STRING);

        return new self($values);
    }

    public static function checkCode(string $code): void
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $code)) {
            throw InvalidValue::because(\sprintf('"%s" is not the code of an option: up to 32 lowercase letters, digits or "_", starting with a letter.', $code));
        }
    }

    public function isEmpty(): bool
    {
        return [] === $this->values;
    }

    public function value(string $code): ?OptionValue
    {
        return $this->values[$code] ?? null;
    }

    /**
     * Sixteen hexadecimal digits that differ for every two sets of chosen values, or `null` for no value.
     */
    public function fingerprint(): ?string
    {
        if ([] === $this->values) {
            return null;
        }

        $chosen = [];

        foreach ($this->values as $code => $value) {
            $chosen[] = [$code, $value->value];
        }

        return substr(hash('sha256', json_encode($chosen, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE)), 0, 16);
    }

    /**
     * The codes of the values still to draw, in code order.
     *
     * @return list<string>
     */
    public function pending(): array
    {
        $pending = [];

        foreach ($this->values as $code => $value) {
            if ($value->isPending()) {
                $pending[] = $code;
            }
        }

        return $pending;
    }

    /**
     * @param list<string> $draws
     */
    public function withDraws(string $code, array $draws): self
    {
        $value = $this->values[$code] ?? throw InvalidValue::because(\sprintf('The configuration has no option %s to draw.', $code));
        $values = $this->values;
        $values[$code] = $value->withDraws($draws);

        return new self($values);
    }
}
