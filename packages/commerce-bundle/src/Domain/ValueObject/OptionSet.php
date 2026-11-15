<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\ValueObject;

use Phil\CommerceBundle\Domain\Exception\InvalidConfiguration;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Money;

/**
 * The options of a sellable, as its catalogue declares them: what checks a line's configuration and gives its
 * supplements. A sellable without options has the empty set, and its lines no configuration.
 */
final readonly class OptionSet
{
    /**
     * @param array<string, OptionDefinition> $options by code, in the order declared
     */
    private function __construct(
        private array $options,
    ) {
    }

    public static function none(): self
    {
        return new self([]);
    }

    public static function of(OptionDefinition ...$options): self
    {
        $keyed = [];

        foreach ($options as $option) {
            if (isset($keyed[$option->code])) {
                throw InvalidValue::because(\sprintf('The option %s is declared twice.', $option->code));
            }

            $keyed[$option->code] = $option;
        }

        return new self($keyed);
    }

    /**
     * @return list<OptionDefinition> in the order declared
     */
    public function options(): array
    {
        return array_values($this->options);
    }

    public function option(string $code): ?OptionDefinition
    {
        return $this->options[$code] ?? null;
    }

    public function isEmpty(): bool
    {
        return [] === $this->options;
    }

    /**
     * The configuration as the line keeps it: each value checked and described by its option, the draw options set,
     * the optional options left unset dropped.
     *
     * @throws InvalidConfiguration for an unknown option, a required one missing or a value refused
     */
    public function check(LineConfiguration $configuration): LineConfiguration
    {
        foreach (array_keys($configuration->values) as $code) {
            if (!isset($this->options[$code])) {
                throw InvalidConfiguration::unknownOption($code);
            }
        }

        $values = [];

        foreach ($this->options as $code => $option) {
            $value = $option->check($configuration->value($code));

            if ($value instanceof OptionValue) {
                $values[$code] = $value;
            }
        }

        return LineConfiguration::of($values);
    }

    /**
     * What each value of the configuration adds to the unit price, with its option, in the order declared; an option
     * the set no longer has, or that adds nothing, is absent.
     *
     * @return list<array{OptionDefinition, Money}>
     */
    public function supplements(LineConfiguration $configuration): array
    {
        $supplements = [];

        foreach ($this->options as $code => $option) {
            $value = $configuration->value($code);
            $supplement = $value instanceof OptionValue ? $option->supplement($value) : null;

            if ($supplement instanceof Money) {
                $supplements[] = [$option, $supplement];
            }
        }

        return $supplements;
    }
}
