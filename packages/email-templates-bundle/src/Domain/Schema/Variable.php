<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Domain\Schema;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\EmailAddress\EmailAddress;
use Phil\EmailAddress\Exception\InvalidEmailAddress;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Stringable;

/**
 * One named slot of a template: its type, whether it is required, its default, the sample value the preview and the
 * checks render with, and, for a list or an object, the variables it holds.
 *
 * A value is checked and normalized by `normalize()`: a violation is recorded under the path of the variable, never
 * with the value itself.
 */
final readonly class Variable
{
    public const int MAX_DESCRIPTION_LENGTH = 500;

    public const int MAX_ITEMS = 1000;

    public const int DEFAULT_MAX_ITEMS = 100;

    public const int MAX_LENGTH = 100_000;

    public const int DEFAULT_MAX_LENGTH = 10_000;

    /** The keys a variable is declared with. */
    public const array KEYS = ['type', 'required', 'default', 'sample', 'description', 'fields', 'items', 'max_items', 'max_length'];

    /** The moment every generated sample date is: fixed, so that a preview does not change from one day to the next. */
    public const string SAMPLE_MOMENT = '2026-01-15T18:30:00+00:00';

    /** The formats a date is read in, the most precise first. */
    private const array DATE_FORMATS = ['Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s.uP', 'Y-m-d\TH:iP', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i', '!Y-m-d'];

    /**
     * @param int|float|string|bool|array<array-key, mixed>|null $default the raw default, as declared
     * @param int|float|string|bool|array<array-key, mixed>|null $sample  the raw sample, as declared
     */
    public function __construct(
        public string $name,
        public VariableType $type,
        public bool $required = false,
        public int|float|string|bool|array|null $default = null,
        public int|float|string|bool|array|null $sample = null,
        public ?string $description = null,
        public ?VariableSchema $fields = null,
        public ?self $items = null,
        public int $maxItems = self::DEFAULT_MAX_ITEMS,
        public int $maxLength = self::DEFAULT_MAX_LENGTH,
    ) {
    }

    /**
     * The declaration of the variable, as `VariableSchema::fromArray()` reads it back.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $spec = ['type' => $this->type->value];

        if ($this->required) {
            $spec['required'] = true;
        }

        if (null !== $this->default) {
            $spec['default'] = $this->default;
        }

        if (null !== $this->sample) {
            $spec['sample'] = $this->sample;
        }

        if (null !== $this->description) {
            $spec['description'] = $this->description;
        }

        if ($this->fields instanceof VariableSchema) {
            $spec['fields'] = $this->fields->toArray();
        }

        if ($this->items instanceof self) {
            $spec['items'] = $this->items->toArray();
        }

        if (VariableType::List === $this->type && self::DEFAULT_MAX_ITEMS !== $this->maxItems) {
            $spec['max_items'] = $this->maxItems;
        }

        if (VariableType::String === $this->type && self::DEFAULT_MAX_LENGTH !== $this->maxLength) {
            $spec['max_length'] = $this->maxLength;
        }

        return $spec;
    }

    /**
     * The value the preview and the checks render with: the sample, else the default, else one made up for the type.
     */
    public function sampleValue(): mixed
    {
        $violations = [];
        $raw = $this->sample ?? $this->default;

        if (null !== $raw) {
            return $this->normalize($raw, $this->name, $violations);
        }

        return match ($this->type) {
            VariableType::String => mb_substr(ucfirst(str_replace('_', ' ', $this->name)), 0, $this->maxLength),
            VariableType::Integer => 1,
            VariableType::Number => 1.5,
            VariableType::Boolean => true,
            VariableType::Datetime => new DateTimeImmutable(self::SAMPLE_MOMENT),
            VariableType::Url => 'https://example.com/',
            VariableType::Email => 'person@example.com',
            VariableType::List => $this->items instanceof self ? [$this->items->sampleValue()] : [],
            VariableType::Object => $this->fields?->samples() ?? [],
        };
    }

    /**
     * The value checked against the type and normalized; `null` with a violation recorded under `$path` when it does
     * not match.
     *
     * @param array<string, string> $violations
     */
    public function normalize(mixed $value, string $path, array &$violations): mixed
    {
        $normalized = match ($this->type) {
            VariableType::String => $this->text($value),
            VariableType::Integer => $this->integer($value),
            VariableType::Number => $this->number($value),
            VariableType::Boolean => \is_bool($value) ? $value : null,
            VariableType::Datetime => $this->moment($value),
            VariableType::Url => $this->url($value),
            VariableType::Email => $this->email($value),
            VariableType::List => $this->list($value, $path, $violations),
            VariableType::Object => $this->object($value, $path, $violations),
        };

        if (null === $normalized && !isset($violations[$path])) {
            $violations[$path] = $this->expectation();
        }

        return $normalized;
    }

    /**
     * What a value of the type must be, for a violation.
     */
    public function expectation(): string
    {
        return match ($this->type) {
            VariableType::String => \sprintf('must be a text of at most %d characters', $this->maxLength),
            VariableType::Integer => 'must be an integer',
            VariableType::Number => 'must be a number',
            VariableType::Boolean => 'must be true or false',
            VariableType::Datetime => 'must be a date and time, a DateTimeInterface or an ISO 8601 text',
            VariableType::Url => 'must be an absolute http or https address',
            VariableType::Email => 'must be an email address',
            VariableType::List => \sprintf('must be a list of at most %d values', $this->maxItems),
            VariableType::Object => 'must be a map of its fields',
        };
    }

    private function text(mixed $value): ?string
    {
        if (\is_int($value) || \is_float($value)) {
            $value = (string) $value;
        } elseif ($value instanceof Stringable) {
            $value = $value->__toString();
        }

        if (!\is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $this->maxLength) {
            return null;
        }

        return $value;
    }

    private function integer(mixed $value): ?int
    {
        if (\is_int($value)) {
            return $value;
        }

        if (\is_string($value) && 1 === preg_match('/^-?[0-9]{1,18}$/D', $value)) {
            return (int) $value;
        }

        return null;
    }

    private function number(mixed $value): int|float|null
    {
        if (\is_int($value) || (\is_float($value) && is_finite($value))) {
            return $value;
        }

        if (\is_string($value) && 1 === preg_match('/^-?[0-9]{1,18}(?:\.[0-9]{1,18})?$/D', $value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return null;
    }

    private function moment(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (!\is_string($value) || \strlen($value) > 40) {
            return null;
        }

        foreach (self::DATE_FORMATS as $format) {
            $moment = DateTimeImmutable::createFromFormat($format, $value);

            if (false !== $moment && 0 === (DateTimeImmutable::getLastErrors()['warning_count'] ?? 0)) {
                return $moment;
            }
        }

        return null;
    }

    private function url(mixed $value): ?string
    {
        if (!\is_string($value) && !$value instanceof Stringable) {
            return null;
        }

        try {
            $url = Url::fromString((string) $value);
        } catch (InvalidUrl) {
            return null;
        }

        return \in_array($url->scheme, ['http', 'https'], true) ? $url->toString() : null;
    }

    private function email(mixed $value): ?string
    {
        if (!\is_string($value) && !$value instanceof Stringable) {
            return null;
        }

        try {
            return EmailAddress::parse((string) $value)->toString();
        } catch (InvalidEmailAddress) {
            return null;
        }
    }

    /**
     * @param array<string, string> $violations
     *
     * @return list<mixed>|null
     */
    private function list(mixed $value, string $path, array &$violations): ?array
    {
        if (!\is_array($value) || !array_is_list($value) || \count($value) > $this->maxItems || !$this->items instanceof self) {
            return null;
        }

        $list = [];

        foreach ($value as $index => $item) {
            $list[] = null === $item ? $this->missing($this->items, $path.'.'.$index, $violations) : $this->items->normalize($item, $path.'.'.$index, $violations);
        }

        return $list;
    }

    /**
     * @param array<string, string> $violations
     *
     * @return array<string, mixed>|null
     */
    private function object(mixed $value, string $path, array &$violations): ?array
    {
        if (!\is_array($value) || ([] !== $value && array_is_list($value)) || !$this->fields instanceof VariableSchema) {
            return null;
        }

        return $this->fields->normalize($value, $path.'.', $violations);
    }

    /**
     * @param array<string, string> $violations
     */
    private function missing(self $variable, string $path, array &$violations): mixed
    {
        $violations[$path] = $variable->expectation();

        return null;
    }
}
