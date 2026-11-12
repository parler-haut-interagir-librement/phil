<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Domain\ValueObject;

use JsonException;
use Phil\FormsAutosave\Domain\Exception\InvalidDraft;

/**
 * What a draft holds: the version of the form's structure it was taken from, and the values of its fields, each name
 * with the list of its values in the order of the form — one for a text field, one per checked box or selected option.
 * Bounded: at most 1,000 names, 1,000 values for a name, and names of at most 255 characters.
 */
final readonly class DraftContent
{
    public const int MAX_NAMES = 1000;

    public const int MAX_VALUES = 1000;

    public const int MAX_NAME_LENGTH = 255;

    /** The characters and length of a structure version. */
    private const string VERSION = '/^[A-Za-z0-9_-]{1,64}$/D';

    /**
     * @param array<string, list<string>> $fields
     */
    private function __construct(
        public string $version,
        public array $fields,
    ) {
    }

    /**
     * @throws InvalidDraft when the version or the fields are not those of a draft
     */
    public static function of(mixed $version, mixed $fields): self
    {
        if (!\is_string($version) || 1 !== preg_match(self::VERSION, $version)) {
            throw InvalidDraft::because('The version of a draft is 1 to 64 letters, digits, "-" or "_".');
        }

        if (!\is_array($fields) || \count($fields) > self::MAX_NAMES) {
            throw InvalidDraft::because('The fields of a draft are an object of at most 1000 names.');
        }

        $checked = [];

        foreach ($fields as $name => $values) {
            $name = (string) $name;

            if ('' === $name || mb_strlen($name) > self::MAX_NAME_LENGTH) {
                throw InvalidDraft::because('The name of a field is 1 to 255 characters.');
            }

            if (!\is_array($values) || !array_is_list($values) || \count($values) > self::MAX_VALUES) {
                throw InvalidDraft::because(\sprintf('The values of "%s" are a list of at most 1000 texts.', $name));
            }

            foreach ($values as $value) {
                if (!\is_string($value)) {
                    throw InvalidDraft::because(\sprintf('The values of "%s" are texts.', $name));
                }
            }

            /** @var list<string> $values */
            $checked[$name] = $values;
        }

        return new self($version, $checked);
    }

    /**
     * @throws InvalidDraft when the text is not the JSON of a draft
     */
    public static function fromJson(string $json): self
    {
        try {
            $decoded = json_decode($json, true, 4, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidDraft::because('A stored draft is a JSON object.');
        }

        if (!\is_array($decoded)) {
            throw InvalidDraft::because('A stored draft is a JSON object.');
        }

        return self::of($decoded['version'] ?? null, $decoded['fields'] ?? null);
    }

    public function toJson(): string
    {
        return json_encode(['version' => $this->version, 'fields' => (object) $this->fields], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }
}
