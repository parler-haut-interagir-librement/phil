<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Application\Maker;

use InvalidArgumentException;

/**
 * What `make:enum` writes: the cases of a new enum, each with its value and its label, from the names given.
 *
 * - **Values** are the names in snake case for an enum backed by strings — `InTransit` and `IN_TRANSIT` both become
 *   `in_transit` —, 1, 2, 3… for an enum backed by integers, none for a pure enum.
 * - **Labels** are the names as words, the first one capitalised: `In transit`.
 * - **Names** are PHP identifiers, each once; a name that is not, or comes twice, is refused.
 */
final readonly class EnumBlueprint
{
    public const string STRING = 'string';

    public const string INT = 'int';

    public const string PURE = 'pure';

    /**
     * @param list<array{name: string, value: int|string|null, label: string}> $cases
     */
    private function __construct(
        public string $backing,
        public array $cases,
        public bool $translatable,
    ) {
    }

    /**
     * @param list<string> $names
     *
     * @throws InvalidArgumentException when the backing is unknown, a name is not a PHP identifier or comes twice, or
     *                                  two names give the same string value
     */
    public static function of(string $backing, array $names, bool $translatable = false): self
    {
        if (!\in_array($backing, [self::STRING, self::INT, self::PURE], true)) {
            throw new InvalidArgumentException(\sprintf('An enum is backed by "string" or "int", or is "pure"; "%s" given.', $backing));
        }

        if ([] === $names) {
            throw new InvalidArgumentException('An enum needs at least one case.');
        }

        foreach ($names as $name) {
            if (1 !== preg_match('/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*$/D', $name)) {
                throw new InvalidArgumentException(\sprintf('"%s" is not a PHP identifier.', $name));
            }
        }

        $duplicates = array_keys(array_filter(array_count_values($names), static fn (int $count): bool => $count > 1));

        if ([] !== $duplicates) {
            throw new InvalidArgumentException(\sprintf('The case %s is named twice.', implode(', ', $duplicates)));
        }

        $cases = [];

        foreach ($names as $index => $name) {
            $cases[] = [
                'name' => $name,
                'value' => match ($backing) {
                    self::STRING => implode('_', self::words($name)),
                    self::INT => $index + 1,
                    default => null,
                },
                'label' => ucfirst(implode(' ', self::words($name))),
            ];
        }

        // A string value is made of the name's words, so `FooBar` and `Foo_Bar`, or `ACTIVE` and `Active`, give the same
        // one: PHP refuses an enum that declares a value twice.
        if (self::STRING === $backing) {
            $named = [];

            foreach ($names as $name) {
                $named[implode('_', self::words($name))][] = $name;
            }

            $clashes = array_filter($named, static fn (array $names): bool => \count($names) > 1);

            if ([] !== $clashes) {
                throw new InvalidArgumentException(\sprintf('Several cases would have the same value: %s; rename them so that their words differ.', implode('; ', array_map(static fn (int|string $value, array $names): string => \sprintf('"%s" for %s', $value, implode(', ', $names)), array_keys($clashes), $clashes))));
            }
        }

        return new self($backing, $cases, $translatable);
    }

    /**
     * The PHP source of the enum, in the namespace and under the short name given: what `make:enum` writes.
     */
    public function source(string $namespace, string $className): string
    {
        $translatable = $this->translatable;
        $lines = ['<?php', '', 'declare(strict_types=1);', '', 'namespace '.$namespace.';', ''];
        $lines[] = 'use Phil\\Enums\\Attribute\\Label;';
        $lines[] = 'use Phil\\Enums\\Attribute\\Meta;';
        $lines[] = 'use Phil\\Enums\\Traits\\AsEnumerableEnumTrait;';

        if ($translatable) {
            $lines[] = 'use Phil\\EnumsBundle\\Infrastructure\\Translation\\AsTranslatableEnumTrait;';
            $lines[] = 'use Symfony\\Contracts\\Translation\\TranslatableInterface;';
        }

        $lines[] = '';
        $lines[] = '#[Meta(Label::class)]';
        $lines[] = 'enum '.$className.(self::PURE === $this->backing ? '' : ': '.$this->backing).($translatable ? ' implements TranslatableInterface' : '');
        $lines[] = '{';
        $lines[] = '    use AsEnumerableEnumTrait;';

        if ($translatable) {
            $lines[] = '    use AsTranslatableEnumTrait;';
        }

        foreach ($this->cases as $case) {
            $lines[] = '';
            $lines[] = '    #[Label('.$this->literal($case['label']).')]';
            $lines[] = '    case '.$case['name'].(null === $case['value'] ? '' : ' = '.$this->literal($case['value'])).';';
        }

        $lines[] = '}';

        return implode("\n", $lines)."\n";
    }

    /**
     * A value as a PHP literal: an integer as written, a string in single quotes, its quotes and backslashes escaped.
     */
    private function literal(int|string $value): string
    {
        return \is_int($value) ? (string) $value : "'".strtr($value, ['\\' => '\\\\', "'" => "\\'"])."'";
    }

    /**
     * The words of a name, lower-cased: `InTransit`, `IN_TRANSIT` and `inTransit` give `in` and `transit`.
     *
     * @return array<string>
     */
    private static function words(string $name): array
    {
        $split = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $name) ?? $name;

        return array_map(mb_strtolower(...), array_filter(explode('_', $split), static fn (string $word): bool => '' !== $word));
    }
}
