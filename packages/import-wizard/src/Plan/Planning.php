<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Plan;

use DateTimeImmutable;
use Phil\ImportWizard\Contract\KeyLookup;
use Phil\ImportWizard\Profile\Column;
use Phil\ImportWizard\Profile\ColumnType;
use Phil\ImportWizard\Profile\ImportProfile;
use Phil\ImportWizard\Profile\Reference;
use Phil\ImportWizard\Source\SourceRow;

/**
 * Internal: the working state of one dry run, made for one call of `ImportPlanner::plan()` and dropped after it.
 */
final class Planning
{
    public const int LOOKUP_CHUNK = 500;

    /** @var array<int, Column> */
    private readonly array $columns;

    /** @var array<string, int> */
    private readonly array $keyPositions;

    /** @var array<int, array{values: array<string, string|int|bool|DateTimeImmutable|null>, key: NaturalKey}> */
    private array $candidates = [];

    /** @var array<int, list<string>> */
    private array $cells = [];

    /** @var array<int, non-empty-list<CellError>> */
    private array $errors = [];

    /** @var array<string, int> the line of each natural key of the file */
    private array $lines = [];

    /** @var array<int, array<string, int>> the line each reference of a row points to in the file */
    private array $dependencies = [];

    /** @var array<string, array<string, NaturalKey>> the keys looked up outside the file, by type */
    private array $outside = [];

    /** @var array<int, array<string, array{string, string}>> the type and key each reference of a row points to outside the file */
    private array $pending = [];

    /** @var array<int, array<string, int>> the references of a row that close a cycle */
    private array $cycles = [];

    /**
     * @param array<int, string> $resolved the column of each header, by position
     */
    public function __construct(private readonly ImportProfile $profile, array $resolved, private readonly int $headerCount)
    {
        $columns = [];

        foreach ($resolved as $index => $name) {
            if (($column = $profile->column($name)) instanceof Column) {
                $columns[$index] = $column;
            }
        }

        $this->columns = $columns;
        $this->keyPositions = array_flip($profile->naturalKey);
    }

    public function read(SourceRow $row): void
    {
        if ($row->isBlank()) {
            return;
        }

        $errors = [];

        if ($row->malformed) {
            $errors[] = new CellError(null, Reason::MalformedRow);
        }

        if ([] !== array_filter(\array_slice($row->cells, $this->headerCount), static fn (string $cell): bool => '' !== trim($cell))) {
            $errors[] = new CellError(null, Reason::TooManyCells);
        }

        $values = [];
        $parts = [];

        foreach ($this->columns as $index => $column) {
            $cell = trim($row->cells[$index] ?? '');

            if ('' === $cell) {
                if ($column->required) {
                    $errors[] = new CellError($column->name, Reason::Required);
                } else {
                    $values[$column->name] = null;
                }

                continue;
            }

            $value = $column->parse($cell);

            if ($value instanceof Reason) {
                $errors[] = new CellError($column->name, $value, $this->detail($column, $value));

                continue;
            }

            $values[$column->name] = $value;

            if (isset($this->keyPositions[$column->name])) {
                $parts[$this->keyPositions[$column->name]] = ColumnType::text($value);
            }
        }

        if ([] === $errors) {
            foreach ($this->profile->rules as $rule) {
                $errors = [...$errors, ...$rule->check($values)];
            }
        }

        $this->cells[$row->line] = $row->cells;

        if ([] !== $errors) {
            $this->errors[$row->line] = $errors;

            return;
        }

        ksort($parts);
        $key = new NaturalKey(array_values($parts));
        $first = $this->lines[$key->toString()] ?? null;

        if (null !== $first) {
            $this->errors[$row->line] = [new CellError(null, Reason::DuplicateKey, null, $first)];

            return;
        }

        $this->lines[$key->toString()] = $row->line;
        $this->candidates[$row->line] = ['values' => $values, 'key' => $key];
    }

    /**
     * @return array{list<PlannedRow>, list<RowRejection>}
     */
    public function decide(KeyLookup $lookup): array
    {
        $this->link();
        $existing = $this->lookUp($lookup);

        foreach ($this->pending as $line => $references) {
            foreach ($references as $column => [$type, $key]) {
                if (!isset($existing[$type][$key])) {
                    $this->errors[$line][] = new CellError($column, Reason::UnknownReference, $type);
                }
            }
        }

        $rows = [];

        foreach ($this->order() as $line) {
            foreach ($this->cycles[$line] ?? [] as $column => $target) {
                $this->errors[$line][] = new CellError($column, Reason::ReferenceCycle, null, $target);
            }

            foreach ($this->dependencies[$line] ?? [] as $column => $target) {
                if (isset($this->errors[$target]) && !isset($this->errors[$line])) {
                    $this->errors[$line] = [new CellError($column, Reason::ReferenceRejected, null, $target)];
                }
            }

            if (!isset($this->errors[$line])) {
                $candidate = $this->candidates[$line];
                $action = isset($existing[$this->profile->type][$candidate['key']->toString()]) ? RowAction::Update : RowAction::Create;
                $rows[] = new PlannedRow($line, $action, $candidate['key'], $candidate['values'], $this->dependencies[$line] ?? [], $this->cells[$line]);
            }
        }

        ksort($this->errors);
        $rejections = [];

        foreach ($this->errors as $line => $errors) {
            $rejections[] = new RowRejection($line, $this->cells[$line], $errors);
        }

        return [$rows, $rejections];
    }

    /**
     * Sorts the references of each row into those whose record is in the file and those to look up outside it.
     */
    private function link(): void
    {
        foreach ($this->candidates as $line => $candidate) {
            foreach ($this->columns as $column) {
                $value = $candidate['values'][$column->name] ?? null;

                if (!$column->reference instanceof Reference || null === $value) {
                    continue;
                }

                $type = $column->reference->type;
                $key = new NaturalKey([ColumnType::text($value)]);
                $text = $key->toString();

                if ($type === $this->profile->type && $text === $candidate['key']->toString()) {
                    $this->errors[$line][] = new CellError($column->name, Reason::SelfReference);
                } elseif ($type === $this->profile->type && isset($this->lines[$text])) {
                    $this->dependencies[$line][$column->name] = $this->lines[$text];
                } else {
                    $this->outside[$type][$text] = $key;
                    $this->pending[$line][$column->name] = [$type, $text];
                }
            }
        }
    }

    /**
     * @return array<string, array<string, NaturalKey>> the keys that exist, by type
     */
    private function lookUp(KeyLookup $lookup): array
    {
        $wanted = $this->outside;

        foreach ($this->candidates as $candidate) {
            $wanted[$this->profile->type][$candidate['key']->toString()] = $candidate['key'];
        }

        $existing = [];

        foreach ($wanted as $type => $keys) {
            foreach (array_chunk($keys, self::LOOKUP_CHUNK) as $chunk) {
                foreach ($lookup->existing($type, $chunk) as $found) {
                    $existing[$type][$found->toString()] = $found;
                }
            }
        }

        return $existing;
    }

    /**
     * The lines of the rows that may be written, each after the rows it refers to; the rows of a cycle are refused.
     *
     * @return list<int>
     */
    private function order(): array
    {
        // true while a row is being walked, false once it is ordered.
        $state = [];
        $order = [];

        foreach (array_keys($this->candidates) as $root) {
            if (isset($state[$root])) {
                continue;
            }

            $state[$root] = true;
            $stack = [[$root, $this->dependencies[$root] ?? []]];
            $positions = [$root => 0];

            while ([] !== $stack) {
                $top = \count($stack) - 1;
                $next = array_shift($stack[$top][1]);

                if (null === $next) {
                    $done = array_pop($stack)[0];
                    $state[$done] = false;
                    $order[] = $done;
                } elseif (!isset($state[$next])) {
                    $state[$next] = true;
                    $positions[$next] = \count($stack);
                    $stack[] = [$next, $this->dependencies[$next] ?? []];
                } elseif ($state[$next]) {
                    $this->refuseCycle(array_column(\array_slice($stack, $positions[$next]), 0));
                }
            }
        }

        return $order;
    }

    /**
     * @param list<int> $members
     */
    private function refuseCycle(array $members): void
    {
        foreach ($members as $line) {
            foreach ($this->dependencies[$line] ?? [] as $column => $target) {
                if (\in_array($target, $members, true)) {
                    $this->cycles[$line][$column] = $target;
                }
            }
        }
    }

    private function detail(Column $column, Reason $reason): ?string
    {
        return match ($reason) {
            Reason::NotAllowed => implode(', ', $column->choices),
            Reason::TooLong => 'at most '.$column->maxLength.' characters',
            default => null,
        };
    }
}
