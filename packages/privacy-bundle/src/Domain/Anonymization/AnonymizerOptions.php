<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Anonymization;

use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

/**
 * The options one column gives its anonymizer, read by type: a wrong type or a missing required option is
 * refused with the option's name, before a row is touched.
 */
final readonly class AnonymizerOptions
{
    /**
     * @param array<array-key, scalar|null> $values
     */
    public function __construct(
        public array $values = [],
    ) {
        foreach (array_keys($values) as $name) {
            if (!\is_string($name)) {
                throw InvalidPrivacyValue::because('Anonymizer options are named: a list of values is not a set of options.');
            }
        }
    }

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->values);
    }

    /**
     * The option as a string; required when no default is given.
     */
    public function string(string $name, ?string $default = null): string
    {
        $value = $this->values[$name] ?? $default;

        if (!\is_string($value)) {
            throw $this->invalid($name, 'a string', $default);
        }

        return $value;
    }

    /**
     * The option as an integer of at least `$minimum`.
     */
    public function int(string $name, int $default, int $minimum = 1): int
    {
        $value = $this->values[$name] ?? $default;

        if (!\is_int($value) || $value < $minimum) {
            throw InvalidPrivacyValue::because(\sprintf('The anonymizer option "%s" must be an integer of at least %d.', $name, $minimum));
        }

        return $value;
    }

    /**
     * The option as given, whatever its scalar type: what a constant writes.
     */
    public function scalar(string $name, string|int|float|bool|null $default): string|int|float|bool|null
    {
        return $this->has($name) ? $this->values[$name] : $default;
    }

    private function invalid(string $name, string $type, ?string $default): InvalidPrivacyValue
    {
        return InvalidPrivacyValue::because(null === $default && !$this->has($name)
            ? \sprintf('The anonymizer option "%s" is required.', $name)
            : \sprintf('The anonymizer option "%s" must be %s.', $name, $type));
    }
}
