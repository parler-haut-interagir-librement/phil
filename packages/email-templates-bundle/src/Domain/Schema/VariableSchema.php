<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Domain\Schema;

use Phil\EmailTemplatesBundle\Domain\Exception\InvalidTemplate;
use Phil\EmailTemplatesBundle\Domain\Exception\InvalidVariables;

/**
 * The named slots of a template: what it may be given, of which type, which are required, their defaults and the
 * sample values its preview renders with. It is checked when a template is saved, and every value given to a render is
 * checked against it.
 *
 * Declared as a map, in YAML, JSON or PHP:
 *
 * ```yaml
 * event:
 *     type: object
 *     required: true
 *     fields:
 *         name: { type: string, required: true, sample: 'Spring meetup' }
 *         starts_at: { type: datetime, sample: '2026-04-18T19:00:00-04:00' }
 * seats: { type: integer, default: 1 }
 * ```
 *
 * A schema holds at most 100 variables, counted at every depth, nested at most 3 deep; a name is a lowercase letter or
 * `_`, then up to 63 lowercase letters, digits or `_`, and `email`, `loop` and the names Twig keeps for itself are
 * reserved.
 */
final readonly class VariableSchema
{
    public const int MAX_VARIABLES = 100;

    public const int MAX_DEPTH = 3;

    /** The names a template reads for itself: the render's own `email`, and those of Twig. */
    public const array RESERVED = ['email', 'loop', '_self', '_context', '_charset', '_key', '_parent', '_seq'];

    private const string NAME = '/^[a-z_][a-z0-9_]{0,63}$/D';

    /**
     * @param array<string, Variable> $variables by name
     */
    private function __construct(
        public array $variables,
    ) {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @param array<array-key, mixed> $spec the declaration, a map of names to their declarations
     *
     * @throws InvalidTemplate listing every reason the declaration is refused
     */
    public static function fromArray(array $spec): self
    {
        $errors = [];
        $count = 0;
        $schema = self::parse($spec, '', 1, $count, $errors);

        if ($count > self::MAX_VARIABLES) {
            $errors[] = \sprintf('A schema declares at most %d variables, counted at every depth; this one declares %d.', self::MAX_VARIABLES, $count);
        }

        if ([] !== $errors) {
            throw InvalidTemplate::withErrors(array_values(array_unique($errors)));
        }

        return $schema;
    }

    /**
     * The declaration, as `fromArray()` reads it back.
     *
     * @return array<string, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(static fn (Variable $variable): array => $variable->toArray(), $this->variables);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->variables);
    }

    public function has(string $name): bool
    {
        return isset($this->variables[$name]);
    }

    /**
     * Each variable's sample value: what the preview and the checks render with.
     *
     * @return array<string, mixed>
     */
    public function samples(): array
    {
        return array_map(static fn (Variable $variable): mixed => $variable->sampleValue(), $this->variables);
    }

    /**
     * The values checked and normalized: each declared variable present, its default or `null` when it was not given.
     *
     * @param array<array-key, mixed> $values
     *
     * @return array<string, mixed>
     *
     * @throws InvalidVariables listing each path that does not match
     */
    public function validate(array $values): array
    {
        $violations = [];
        $normalized = $this->normalize($values, '', $violations);

        if ([] !== $violations) {
            throw InvalidVariables::withViolations($violations);
        }

        return $normalized;
    }

    /**
     * @param array<array-key, mixed> $values
     * @param array<string, string>   $violations
     *
     * @return array<string, mixed>
     *
     * @internal what `validate()` and the objects of a schema share
     */
    public function normalize(array $values, string $prefix, array &$violations): array
    {
        foreach (array_keys($values) as $name) {
            if (!\is_string($name) || !isset($this->variables[$name])) {
                $violations[$prefix.$name] = 'is not declared in the schema';
            }
        }

        $normalized = [];

        foreach ($this->variables as $name => $variable) {
            $value = $values[$name] ?? null;

            if (null === $value && null !== $variable->default) {
                $value = $variable->default;
            }

            if (null === $value) {
                if ($variable->required) {
                    $violations[$prefix.$name] = 'is required';
                }

                $normalized[$name] = null;

                continue;
            }

            $normalized[$name] = $variable->normalize($value, $prefix.$name, $violations);
        }

        return $normalized;
    }

    /**
     * @param array<array-key, mixed> $spec
     * @param list<string>            $errors
     */
    private static function parse(array $spec, string $prefix, int $depth, int &$count, array &$errors): self
    {
        $variables = [];

        foreach ($spec as $name => $declaration) {
            $path = $prefix.$name;

            if (!\is_string($name) || 1 !== preg_match(self::NAME, $name)) {
                $errors[] = \sprintf('"%s" is not a variable name: a lowercase letter or "_", then up to 63 lowercase letters, digits or "_".', $path);

                continue;
            }

            if ('' === $prefix && \in_array($name, self::RESERVED, true)) {
                $errors[] = \sprintf('"%s" is a name the template keeps for itself.', $name);

                continue;
            }

            if (!\is_array($declaration)) {
                $errors[] = \sprintf('"%s" must be declared as a map, with at least its "type".', $path);

                continue;
            }

            ++$count;
            $variable = self::variable($name, $declaration, $path, $depth, $count, $errors);

            if ($variable instanceof Variable) {
                $variables[$name] = $variable;
            }
        }

        return new self($variables);
    }

    /**
     * @param array<array-key, mixed> $declaration
     * @param list<string>            $errors
     */
    private static function variable(string $name, array $declaration, string $path, int $depth, int &$count, array &$errors): ?Variable
    {
        $before = \count($errors);

        foreach (array_keys($declaration) as $key) {
            if (!\in_array($key, Variable::KEYS, true)) {
                $errors[] = \sprintf('"%s" has an unknown key "%s"; the keys are %s.', $path, $key, implode(', ', Variable::KEYS));
            }
        }

        $type = \is_string($declaration['type'] ?? null) ? VariableType::tryFrom($declaration['type']) : null;

        if (!$type instanceof VariableType) {
            $errors[] = \sprintf('"%s" has no type, or one that does not exist; the types are %s.', $path, implode(', ', array_map(static fn (VariableType $case): string => $case->value, VariableType::cases())));

            return null;
        }

        if ($type->isComposite() && $depth > self::MAX_DEPTH) {
            $errors[] = \sprintf('"%s" is a %s nested more than %d levels deep.', $path, $type->value, self::MAX_DEPTH);

            return null;
        }

        [$required, $description, $maxItems, $maxLength] = self::options($declaration, $type, $path, $errors);
        [$fields, $items] = self::nested($declaration, $type, $path, $depth, $count, $errors);

        if (\count($errors) > $before) {
            return null;
        }

        $variable = new Variable($name, $type, $required, null, null, $description, $fields, $items, $maxItems, $maxLength);
        $default = self::checkedValue($variable, $declaration, 'default', $path, $errors);
        $sample = self::checkedValue($variable, $declaration, 'sample', $path, $errors);

        // With a reason listed, the schema is refused whole: the variable is not read again.
        return new Variable($name, $type, $required, $default, $sample, $description, $fields, $items, $maxItems, $maxLength);
    }

    /**
     * Whether the variable is required, its description, and the limits of a list and of a text.
     *
     * @param array<array-key, mixed> $declaration
     * @param list<string>            $errors
     *
     * @return array{bool, ?string, int, int}
     */
    private static function options(array $declaration, VariableType $type, string $path, array &$errors): array
    {
        $required = $declaration['required'] ?? false;
        $description = $declaration['description'] ?? null;
        $maxItems = $declaration['max_items'] ?? Variable::DEFAULT_MAX_ITEMS;
        $maxLength = $declaration['max_length'] ?? Variable::DEFAULT_MAX_LENGTH;

        if (!\is_bool($required)) {
            $errors[] = \sprintf('"%s": "required" is true or false.', $path);
        }

        if (null !== $description && (!\is_string($description) || mb_strlen($description) > Variable::MAX_DESCRIPTION_LENGTH)) {
            $errors[] = \sprintf('"%s": "description" is a text of at most %d characters.', $path, Variable::MAX_DESCRIPTION_LENGTH);
            $description = null;
        }

        if (!\is_int($maxItems) || $maxItems < 1 || $maxItems > Variable::MAX_ITEMS || (VariableType::List !== $type && isset($declaration['max_items']))) {
            $errors[] = \sprintf('"%s": "max_items" is given to a list only, from 1 to %d.', $path, Variable::MAX_ITEMS);
            $maxItems = Variable::DEFAULT_MAX_ITEMS;
        }

        if (!\is_int($maxLength) || $maxLength < 1 || $maxLength > Variable::MAX_LENGTH || (VariableType::String !== $type && isset($declaration['max_length']))) {
            $errors[] = \sprintf('"%s": "max_length" is given to a string only, from 1 to %d.', $path, Variable::MAX_LENGTH);
            $maxLength = Variable::DEFAULT_MAX_LENGTH;
        }

        return [true === $required, $description, $maxItems, $maxLength];
    }

    /**
     * The fields of an object, or the items of a list.
     *
     * @param array<array-key, mixed> $declaration
     * @param list<string>            $errors
     *
     * @return array{?self, ?Variable}
     */
    private static function nested(array $declaration, VariableType $type, string $path, int $depth, int &$count, array &$errors): array
    {
        $fields = null;
        $items = null;
        $spec = $declaration['fields'] ?? null;

        if (VariableType::Object === $type) {
            if (\is_array($spec) && [] !== $spec) {
                $fields = self::parse($spec, $path.'.', $depth + 1, $count, $errors);
            } else {
                $errors[] = \sprintf('"%s" is an object: it declares its "fields".', $path);
            }
        } elseif (null !== $spec) {
            $errors[] = \sprintf('"%s": "fields" is given to an object only.', $path);
        }

        $spec = $declaration['items'] ?? null;

        if (VariableType::List === $type) {
            if (\is_array($spec)) {
                ++$count;
                $items = self::variable('item', $spec, $path.'.items', $depth + 1, $count, $errors);
            } else {
                $errors[] = \sprintf('"%s" is a list: it declares its "items".', $path);
            }
        } elseif (null !== $spec) {
            $errors[] = \sprintf('"%s": "items" is given to a list only.', $path);
        }

        return [$fields, $items];
    }

    /**
     * The default or the sample as declared, once it matches the type of the variable.
     *
     * @param array<array-key, mixed> $declaration
     * @param list<string>            $errors
     *
     * @return int|float|string|bool|array<array-key, mixed>|null
     */
    private static function checkedValue(Variable $variable, array $declaration, string $key, string $path, array &$errors): int|float|string|bool|array|null
    {
        $value = $declaration[$key] ?? null;

        if (null === $value) {
            return null;
        }

        if (!\is_scalar($value) && !\is_array($value)) {
            $errors[] = \sprintf('The %s of "%s" is a text, a number, a boolean, a list or a map.', $key, $path);

            return null;
        }

        $violations = [];
        $variable->normalize($value, $path, $violations);

        foreach ($violations as $where => $reason) {
            $errors[] = \sprintf('The %s of "%s" does not match its type: "%s" %s.', $key, $path, $where, $reason);
        }

        return $value;
    }
}
