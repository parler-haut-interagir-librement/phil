<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Profile;

use DateTimeImmutable;
use Phil\ImportWizard\Exception\InvalidProfile;
use Phil\ImportWizard\Plan\Reason;

/**
 * A column of an importable type: its name, its type, whether it is required, the header a template writes, the other
 * headers it is recognised by, and its checks — a length, a list of allowed values, a reference.
 */
final readonly class Column
{
    public const string NAME_PATTERN = '/^[a-z][a-z0-9_]{0,63}$/D';

    private const int MAX_ALIASES = 32;

    private const int MAX_CHOICES = 256;

    /** The header a template writes, the name when none is given. */
    public string $header;

    /**
     * @param list<string> $aliases other headers the column is recognised by, in any case and with or without accents
     * @param list<string> $choices the allowed values of a text column, matched in any case and kept as written here
     */
    public function __construct(
        public string $name,
        public ColumnType $type = ColumnType::Text,
        public bool $required = false,
        ?string $header = null,
        public array $aliases = [],
        public ?int $maxLength = null,
        public array $choices = [],
        public ?Reference $reference = null,
    ) {
        if (1 !== preg_match(self::NAME_PATTERN, $name)) {
            throw InvalidProfile::because(\sprintf('The column name "%s" must be lower-case letters, digits and underscores.', $name));
        }

        $this->header = $header ?? $name;

        if ('' === trim($this->header) || \count($aliases) > self::MAX_ALIASES || \count($choices) > self::MAX_CHOICES) {
            throw InvalidProfile::because(\sprintf('The column "%s" needs a header, at most 32 aliases and 256 choices.', $name));
        }

        if (null !== $maxLength && $maxLength < 1) {
            throw InvalidProfile::because(\sprintf('The maximum length of the column "%s" must be positive.', $name));
        }

        if ([] !== $choices && ColumnType::Text !== $type) {
            throw InvalidProfile::because(\sprintf('The column "%s" has choices: it must be a text column.', $name));
        }
    }

    /**
     * Reads a trimmed, non-empty cell: the typed value, or the reason it is refused.
     */
    public function parse(string $cell): string|int|bool|DateTimeImmutable|Reason
    {
        if (null !== $this->maxLength && mb_strlen($cell) > $this->maxLength) {
            return Reason::TooLong;
        }

        $value = $this->type->parse($cell);

        if ([] === $this->choices || !\is_string($value)) {
            return $value;
        }

        $folded = mb_strtolower($value);

        foreach ($this->choices as $choice) {
            if (mb_strtolower($choice) === $folded) {
                return $choice;
            }
        }

        return Reason::NotAllowed;
    }

    /**
     * @return array{name: string, type: string, required: bool, header: string, aliases: list<string>, maxLength: int|null, choices: list<string>, reference: string|null}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type->value,
            'required' => $this->required,
            'header' => $this->header,
            'aliases' => $this->aliases,
            'maxLength' => $this->maxLength,
            'choices' => $this->choices,
            'reference' => $this->reference?->type,
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data, string $path = 'column'): self
    {
        $type = ColumnType::tryFrom(ProfileData::string($data, 'type', $path))
            ?? throw InvalidProfile::because(\sprintf('The field "%s.type" must be one of the column types.', $path));
        $reference = ProfileData::optionalString($data, 'reference', $path);

        return new self(
            ProfileData::string($data, 'name', $path),
            $type,
            ProfileData::bool($data, 'required', $path),
            ProfileData::optionalString($data, 'header', $path),
            ProfileData::strings($data, 'aliases', $path),
            ProfileData::optionalInt($data, 'maxLength', $path),
            ProfileData::strings($data, 'choices', $path),
            null === $reference ? null : new Reference($reference),
        );
    }
}
