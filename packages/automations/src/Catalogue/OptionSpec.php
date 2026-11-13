<?php

declare(strict_types=1);

namespace Phil\Automations\Catalogue;

use Phil\Automations\Definition\Options;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;

/**
 * One option a trigger or an action takes: its name, its type, whether a definition must give it, the label a builder
 * shows, and, for a choice, the values allowed.
 */
final readonly class OptionSpec
{
    /**
     * @param list<string> $choices
     */
    public function __construct(
        public string $name,
        public OptionType $type,
        public bool $required = false,
        public string $label = '',
        public array $choices = [],
    ) {
    }

    /**
     * What is wrong with this option in a set of options; `null` when nothing is.
     */
    public function problem(Options $options): ?string
    {
        if (!$options->has($this->name) || null === $options->get($this->name)) {
            return $this->required ? \sprintf('The option "%s" is required.', $this->name) : null;
        }

        $value = $options->get($this->name);

        $valid = match ($this->type) {
            OptionType::Text => \is_string($value),
            OptionType::Integer => \is_int($value),
            OptionType::Boolean => \is_bool($value),
            OptionType::Identifier => \is_string($value) && self::isIdentifier($value),
            OptionType::Url => \is_string($value) && $this->isWebAddress($value),
            OptionType::Choice => \is_string($value) && \in_array($value, $this->choices, true),
            OptionType::Identifiers => \is_array($value) && [] === array_filter($value, static fn (int|string $item): bool => !\is_string($item) || !self::isIdentifier($item)),
        };

        return $valid ? null : \sprintf('The option "%s" is not a valid %s.', $this->name, $this->type->value);
    }

    private static function isIdentifier(string $value): bool
    {
        return 1 === preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:\/-]{0,254}$/D', $value);
    }

    private function isWebAddress(string $value): bool
    {
        try {
            return 'https' === Url::fromString($value)->scheme;
        } catch (InvalidUrl) {
            return false;
        }
    }
}
