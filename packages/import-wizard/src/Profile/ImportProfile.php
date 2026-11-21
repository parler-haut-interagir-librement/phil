<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Profile;

use JsonException;
use Phil\ImportWizard\Contract\RowRule;
use Phil\ImportWizard\Exception\InvalidProfile;
use Phil\ImportWizard\Rule\DateOrder;

/**
 * What a file of one importable type holds: its columns, the natural key that tells an update from a creation, the
 * rules across a row, and the mapping of the headers kept from the last import. A profile is a value: it serialises
 * to an array or JSON and reads back the same, so that an import is replayed on the next file.
 */
final readonly class ImportProfile
{
    public const string TYPE_PATTERN = '/^[a-z][a-z0-9-]{0,63}$/D';

    private const int FORMAT = 1;

    private const int MAX_COLUMNS = 256;

    private const int MAX_JSON_BYTES = 1_048_576;

    /** @var array<string, Column> */
    private array $byName;

    /**
     * @param non-empty-list<Column> $columns
     * @param non-empty-list<string> $naturalKey the required columns whose values identify a record
     * @param list<RowRule>          $rules
     */
    public function __construct(
        public string $type,
        public array $columns,
        public array $naturalKey,
        public array $rules = [],
        public ?ColumnMapping $mapping = null,
    ) {
        if (1 !== preg_match(self::TYPE_PATTERN, $type)) {
            throw InvalidProfile::because(\sprintf('The type "%s" must be lower-case letters, digits and hyphens.', $type));
        }

        if (\count($columns) > self::MAX_COLUMNS) {
            throw InvalidProfile::because(\sprintf('The profile "%s" has more than 256 columns.', $type));
        }

        $byName = [];

        foreach ($columns as $column) {
            if (isset($byName[$column->name])) {
                throw InvalidProfile::because(\sprintf('The column "%s" is declared twice.', $column->name));
            }

            $byName[$column->name] = $column;
        }

        $this->byName = $byName;
        $this->checkNaturalKey();

        foreach ($mapping?->toArray() ?? [] as $pair) {
            if (!isset($byName[$pair['column']])) {
                throw InvalidProfile::because(\sprintf('The mapping names the column "%s", which the profile does not have.', $pair['column']));
            }
        }
    }

    public function column(string $name): ?Column
    {
        return $this->byName[$name] ?? null;
    }

    /**
     * The same profile, keeping this mapping for the next file.
     */
    public function withMapping(ColumnMapping $mapping): self
    {
        return new self($this->type, $this->columns, $this->naturalKey, $this->rules, $mapping);
    }

    /**
     * @return array{format: int, type: string, columns: list<array<string, mixed>>, naturalKey: list<string>, rules: list<array<string, scalar|null>>, mapping: list<array{header: string, column: string}>|null}
     */
    public function toArray(): array
    {
        return [
            'format' => self::FORMAT,
            'type' => $this->type,
            'columns' => array_map(static fn (Column $column): array => $column->toArray(), $this->columns),
            'naturalKey' => $this->naturalKey,
            'rules' => array_map(static fn (RowRule $rule): array => ['rule' => $rule::name(), ...$rule->toArray()], $this->rules),
            'mapping' => $this->mapping?->toArray(),
        ];
    }

    /**
     * @param array<array-key, mixed>     $data
     * @param list<class-string<RowRule>> $ruleTypes the rules an application adds to `DateOrder`
     */
    public static function fromArray(array $data, array $ruleTypes = []): self
    {
        if (self::FORMAT !== ($data['format'] ?? null)) {
            throw InvalidProfile::because('The field "profile.format" must be 1.');
        }

        $rules = [];
        $known = [];

        foreach ([DateOrder::class, ...$ruleTypes] as $ruleType) {
            $known[$ruleType::name()] = $ruleType;
        }

        foreach (ProfileData::arrays($data, 'rules', 'profile') as $index => $rule) {
            $ruleType = $known[ProfileData::string($rule, 'rule', 'profile.rules['.$index.']')]
                ?? throw InvalidProfile::because(\sprintf('The rule of "profile.rules[%d]" is not known.', $index));
            $rules[] = $ruleType::fromArray($rule);
        }

        $columns = [];

        foreach (ProfileData::arrays($data, 'columns', 'profile') as $index => $column) {
            $columns[] = Column::fromArray($column, 'profile.columns['.$index.']');
        }

        $naturalKey = ProfileData::strings($data, 'naturalKey', 'profile');

        if ([] === $columns || [] === $naturalKey) {
            throw InvalidProfile::because('The fields "profile.columns" and "profile.naturalKey" must not be empty.');
        }

        $mapping = null === ($data['mapping'] ?? null) ? null : ColumnMapping::fromArray(ProfileData::arrays($data, 'mapping', 'profile'));

        return new self(ProfileData::string($data, 'type', 'profile'), $columns, $naturalKey, $rules, $mapping);
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRETTY_PRINT);
    }

    /**
     * @param list<class-string<RowRule>> $ruleTypes
     */
    public static function fromJson(string $json, array $ruleTypes = []): self
    {
        if (\strlen($json) > self::MAX_JSON_BYTES) {
            throw InvalidProfile::because('A profile is at most 1 MiB of JSON.');
        }

        try {
            $data = json_decode($json, true, 16, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidProfile::because('The profile is not valid JSON.');
        }

        return \is_array($data) ? self::fromArray($data, $ruleTypes) : throw InvalidProfile::because('The profile must be a JSON object.');
    }

    private function checkNaturalKey(): void
    {
        if (\count(array_unique($this->naturalKey)) !== \count($this->naturalKey)) {
            throw InvalidProfile::because(\sprintf('The natural key of "%s" names a column twice.', $this->type));
        }

        foreach ($this->naturalKey as $name) {
            if (true !== $this->column($name)?->required) {
                throw InvalidProfile::because(\sprintf('The natural key names "%s", which must be a required column.', $name));
            }
        }

        foreach ($this->columns as $column) {
            if ($column->reference?->type === $this->type && 1 !== \count($this->naturalKey)) {
                throw InvalidProfile::because(\sprintf('The column "%s" refers to "%s", whose natural key must be one column.', $column->name, $this->type));
            }
        }
    }
}
