<?php

declare(strict_types=1);

namespace Phil\BulkActions\Selection;

use Phil\BulkActions\Exception\InvalidInput;
use Phil\BulkActions\Internal\Check;

/**
 * The items a bulk action applies to: identifiers, or every item of a filter. It holds no class name; the target, from
 * the configuration, says what the identifiers are.
 */
final readonly class Selection
{
    /** The most identifiers a selection can list, explicit or unticked, whatever the limits say. */
    public const int MAX_IDENTIFIERS = 10_000;

    /**
     * @param list<string>          $identifiers the items, without duplicates, in the order given
     * @param array<string, string> $versions    the version each item had when the person saw it, by identifier
     * @param list<string>          $excluded    the items of the filter the person unticked
     */
    private function __construct(
        public SelectionKind $kind,
        public array $identifiers,
        public array $versions,
        public ?FilterCriteria $filter,
        public array $excluded,
    ) {
    }

    /**
     * @param list<int|string>              $identifiers the ticked items
     * @param array<int|string, int|string> $versions    the version shown for each item, when the target keeps one
     */
    public static function identifiers(array $identifiers, array $versions = []): self
    {
        $ids = self::unique('identifier', $identifiers);

        if ([] === $ids) {
            throw InvalidInput::because('selection', 'must name at least one item');
        }

        $known = array_flip($ids);
        $checked = [];

        foreach ($versions as $id => $version) {
            $id = Check::identifier('identifier', $id);

            if (!isset($known[$id])) {
                throw InvalidInput::because('version of "'.$id.'"', 'must belong to a selected item');
            }

            $checked[$id] = Check::identifier('version', $version);
        }

        return new self(SelectionKind::Identifiers, $ids, $checked, null, []);
    }

    /**
     * @param list<int|string> $excluded the items of the filter the person unticked
     */
    public static function filter(FilterCriteria $filter, array $excluded = []): self
    {
        return new self(SelectionKind::Filter, [], [], $filter, self::unique('excluded identifier', $excluded));
    }

    /**
     * The version the person saw for an item, or null when the selection did not carry one.
     */
    public function versionOf(string $identifier): ?string
    {
        return $this->versions[$identifier] ?? null;
    }

    /**
     * @return array{kind: string, identifiers: list<string>, versions: array<string, string>, filter: array{name: string, parameters: array<string, scalar|list<scalar|null>|null>}|null, excluded: list<string>}
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind->value,
            'identifiers' => $this->identifiers,
            'versions' => $this->versions,
            'filter' => $this->filter?->toArray(),
            'excluded' => $this->excluded,
        ];
    }

    /**
     * @param array<array-key, mixed> $data what toArray() gave, read back from storage
     */
    public static function fromArray(array $data): self
    {
        $kind = SelectionKind::tryFrom(\is_string($data['kind'] ?? null) ? $data['kind'] : '');
        $identifiers = $data['identifiers'] ?? [];
        $versions = $data['versions'] ?? [];
        $filter = $data['filter'] ?? null;
        $excluded = $data['excluded'] ?? [];

        if (null === $kind || !self::isIdList($identifiers) || !self::isIdList($excluded) || !\is_array($versions)) {
            throw InvalidInput::because('stored selection', 'must have a kind and lists of identifiers');
        }

        if (SelectionKind::Identifiers === $kind) {
            /** @var array<int|string, int|string> $versions */
            return self::identifiers($identifiers, $versions);
        }

        if (!\is_array($filter)) {
            throw InvalidInput::because('stored selection', 'must have its filter');
        }

        return self::filter(FilterCriteria::fromArray($filter), $excluded);
    }

    /**
     * @param list<int|string> $values
     *
     * @return list<string>
     */
    private static function unique(string $field, array $values): array
    {
        if (\count($values) > self::MAX_IDENTIFIERS) {
            throw InvalidInput::because('selection', \sprintf('must list %d identifiers at most', self::MAX_IDENTIFIERS));
        }

        $unique = [];

        foreach ($values as $value) {
            $unique[Check::identifier($field, $value)] = true;
        }

        return array_map(strval(...), array_keys($unique));
    }

    /**
     * @phpstan-assert-if-true list<int|string> $value
     */
    private static function isIdList(mixed $value): bool
    {
        if (!\is_array($value) || !array_is_list($value)) {
            return false;
        }

        return array_all($value, static fn (mixed $item): bool => \is_string($item) || \is_int($item));
    }
}
