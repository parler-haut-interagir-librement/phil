<?php

declare(strict_types=1);

namespace Phil\BulkActions\Selection;

use Phil\BulkActions\Exception\InvalidInput;
use Phil\BulkActions\Internal\Check;

/**
 * "Every item of this filter": a filter the target's repository declares, by name, with its parameters. It is kept
 * instead of the identifiers it matches, and the repository reads it again when the run executes, so a selection of
 * 50 000 items is a few bytes and never a form of 50 000 fields.
 */
final readonly class FilterCriteria
{
    /** The most parameters a filter takes. */
    public const int MAX_PARAMETERS = 32;

    /** The largest filter, its parameters encoded as JSON, in bytes. */
    public const int MAX_BYTES = 16_384;

    /**
     * @param array<string, scalar|list<scalar|null>|null> $parameters sorted by name
     */
    private function __construct(
        public string $name,
        public array $parameters,
    ) {
    }

    /**
     * @param string                  $name       the filter's name, as the repository knows it
     * @param array<array-key, mixed> $parameters what the person chose, such as ["status" => "draft"]
     */
    public static function of(string $name, array $parameters = []): self
    {
        Check::name('filter name', $name);

        if (\count($parameters) > self::MAX_PARAMETERS) {
            throw InvalidInput::because('filter', \sprintf('must have %d parameters at most', self::MAX_PARAMETERS));
        }

        $checked = [];

        foreach ($parameters as $key => $value) {
            $key = Check::name('filter parameter', (string) $key);
            $checked[$key] = Check::value('filter parameter "'.$key.'"', $value);
        }

        ksort($checked, \SORT_STRING);
        $criteria = new self($name, $checked);

        if (\strlen($criteria->encoded()) > self::MAX_BYTES) {
            throw InvalidInput::because('filter', \sprintf('must have %d bytes at most once encoded', self::MAX_BYTES));
        }

        return $criteria;
    }

    /**
     * @return array{name: string, parameters: array<string, scalar|list<scalar|null>|null>}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'parameters' => $this->parameters];
    }

    /**
     * @param array<array-key, mixed> $data what toArray() gave, read back from storage
     */
    public static function fromArray(array $data): self
    {
        $name = $data['name'] ?? null;
        $parameters = $data['parameters'] ?? [];

        if (!\is_string($name) || !\is_array($parameters)) {
            throw InvalidInput::because('stored filter', 'must have a name and parameters');
        }

        return self::of($name, $parameters);
    }

    private function encoded(): string
    {
        return json_encode($this->toArray(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
    }
}
