<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Profile;

use Phil\ImportWizard\Exception\InvalidProfile;

/**
 * Which header of a file fills which column of a profile. A mapping is guessed from the headers, adjusted by the person
 * who imports, and kept in the profile so that the next file with the same headers is read the same way. A header is
 * found as written, then folded: case, accents and punctuation aside. A header mapped to nothing is ignored.
 */
final readonly class ColumnMapping
{
    /** @var list<array{header: string, column: string}> */
    private array $pairs;

    /**
     * @param list<array{header: string, column: string}> $pairs one header per column and one column per header
     */
    public function __construct(array $pairs = [])
    {
        $headers = [];
        $columns = [];

        foreach ($pairs as $pair) {
            if (isset($headers[$pair['header']]) || isset($columns[$pair['column']])) {
                throw InvalidProfile::because(\sprintf('The column "%s" or its header is mapped twice.', $pair['column']));
            }

            $headers[$pair['header']] = $pair['column'];
            $columns[$pair['column']] = $pair['header'];
        }

        $this->pairs = $pairs;
    }

    /**
     * The mapping of the headers to the columns whose name, header or aliases they match, folded.
     *
     * @param list<string> $headers
     */
    public static function guess(array $headers, ImportProfile $profile): self
    {
        return new self()->completedFor($headers, $profile);
    }

    /**
     * This mapping, without the pairs whose header or column is gone, and with a guess for each column still unmapped
     * among the headers still free.
     *
     * @param list<string> $headers
     */
    public function completedFor(array $headers, ImportProfile $profile): self
    {
        $pairs = [];
        $taken = [];

        foreach ($this->pairs as $pair) {
            $index = $this->find($pair['header'], $headers);

            if (null !== $index && !isset($taken[$index]) && $profile->column($pair['column']) instanceof Column) {
                $pairs[] = $pair;
                $taken[$index] = $pair['column'];
            }
        }

        $mapped = array_column($pairs, 'column', 'column');

        foreach ($profile->columns as $column) {
            if (isset($mapped[$column->name])) {
                continue;
            }

            $names = array_map(HeaderName::fold(...), [$column->name, $column->header, ...$column->aliases]);

            foreach ($headers as $index => $header) {
                if (!isset($taken[$index]) && \in_array(HeaderName::fold($header), $names, true)) {
                    $pairs[] = ['header' => $header, 'column' => $column->name];
                    $taken[$index] = $column->name;

                    break;
                }
            }
        }

        return new self($pairs);
    }

    /**
     * This mapping with the header filling the column, in place of what either was mapped to.
     */
    public function with(string $header, string $column): self
    {
        $pairs = array_filter($this->pairs, static fn (array $pair): bool => $pair['header'] !== $header && $pair['column'] !== $column);

        return new self([...$pairs, ['header' => $header, 'column' => $column]]);
    }

    /**
     * This mapping with the header ignored.
     */
    public function without(string $header): self
    {
        return new self(array_values(array_filter($this->pairs, static fn (array $pair): bool => $pair['header'] !== $header)));
    }

    /**
     * The column each header of a file fills, by the position of the header.
     *
     * @param list<string> $headers
     *
     * @return array<int, string>
     */
    public function resolve(array $headers): array
    {
        $resolved = [];

        foreach ($this->pairs as $pair) {
            $index = $this->find($pair['header'], $headers);

            if (null !== $index && !isset($resolved[$index])) {
                $resolved[$index] = $pair['column'];
            }
        }

        ksort($resolved);

        return $resolved;
    }

    /**
     * The header mapped to the column, or null.
     */
    public function headerOf(string $column): ?string
    {
        foreach ($this->pairs as $pair) {
            if ($pair['column'] === $column) {
                return $pair['header'];
            }
        }

        return null;
    }

    /**
     * @return list<array{header: string, column: string}>
     */
    public function toArray(): array
    {
        return $this->pairs;
    }

    /**
     * @param list<array<array-key, mixed>> $pairs
     */
    public static function fromArray(array $pairs, string $path = 'mapping'): self
    {
        $read = [];

        foreach ($pairs as $index => $pair) {
            $read[] = [
                'header' => ProfileData::string($pair, 'header', $path.'['.$index.']'),
                'column' => ProfileData::string($pair, 'column', $path.'['.$index.']'),
            ];
        }

        return new self($read);
    }

    /**
     * @param list<string> $headers
     */
    private function find(string $header, array $headers): ?int
    {
        $exact = array_search($header, $headers, true);

        if (\is_int($exact)) {
            return $exact;
        }

        $folded = HeaderName::fold($header);

        if ('' === $folded) {
            return null;
        }

        foreach ($headers as $index => $candidate) {
            if (HeaderName::fold($candidate) === $folded) {
                return $index;
            }
        }

        return null;
    }
}
