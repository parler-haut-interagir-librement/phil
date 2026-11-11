<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate;

use Phil\CommerceProductTemplate\Exception\InvalidDefinition;

/**
 * How a template writes the SKU of each product: literal characters and placeholders in braces, the codes glued
 * together or separated — `{design}{lang}{version}{size}{colour}`, `{design}-{size}-{colour}`.
 *
 * `{design}` is the design's code, `{lang}` the code of its language version (empty for a design in one language),
 * `{template}` the template's code, and any other placeholder the code of the value of the axis of that name.
 */
final readonly class SkuPattern
{
    public const int MAX_LENGTH = 128;

    /**
     * @param non-empty-list<array{bool, string}> $parts each a placeholder (`true`) and its name, or literal characters
     */
    private function __construct(
        public string $pattern,
        private array $parts,
    ) {
    }

    /**
     * @param string $pattern up to 128 characters: letters, digits, ".", "_" and "-", and placeholders `{name}`, each
     *                        once, `{design}` among them
     */
    public static function parse(string $pattern): self
    {
        if ('' === $pattern || \strlen($pattern) > self::MAX_LENGTH) {
            throw InvalidDefinition::because(\sprintf('A SKU pattern holds 1 to %d characters.', self::MAX_LENGTH));
        }

        if (1 !== preg_match('/^(?:[A-Za-z0-9._-]+|\{[a-z][a-z0-9_]{0,31}\})+$/D', $pattern)) {
            throw InvalidDefinition::because(\sprintf('"%s" is not a SKU pattern: letters, digits, ".", "_", "-" and placeholders such as {size}.', $pattern));
        }

        preg_match_all('/[A-Za-z0-9._-]+|\{([a-z][a-z0-9_]*)\}/', $pattern, $matches, \PREG_SET_ORDER | \PREG_UNMATCHED_AS_NULL);
        $parts = [];
        $names = [];

        foreach ($matches as $match) {
            $name = $match[1];

            if (null === $name) {
                $parts[] = [false, $match[0]];

                continue;
            }

            if (isset($names[$name])) {
                throw InvalidDefinition::because(\sprintf('The SKU pattern "%s" names {%s} twice.', $pattern, $name));
            }

            $names[$name] = $name;
            $parts[] = [true, $name];
        }

        if (!isset($names['design'])) {
            throw InvalidDefinition::because(\sprintf('The SKU pattern "%s" names {design}: two designs never share a SKU.', $pattern));
        }

        \assert([] !== $parts);

        return new self($pattern, $parts);
    }

    /**
     * @return list<string> the names of its placeholders, in order
     */
    public function placeholders(): array
    {
        return array_values(array_map(static fn (array $part): string => $part[1], array_filter($this->parts, static fn (array $part): bool => $part[0])));
    }

    /**
     * The SKU, each placeholder replaced by its value.
     *
     * @param array<string, string> $values by placeholder name, one for each
     */
    public function render(array $values): string
    {
        $sku = '';

        foreach ($this->parts as [$placeholder, $text]) {
            $sku .= $placeholder ? ($values[$text] ?? throw InvalidDefinition::because(\sprintf('The SKU pattern "%s" has no value for {%s}.', $this->pattern, $text))) : $text;
        }

        return $sku;
    }
}
