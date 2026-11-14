<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\State;

/**
 * The addresses of a table's states: a query string alone, so that a link goes on working wherever the table is drawn
 * from, on the page or from the component's own route.
 */
final readonly class TableLinks
{
    /**
     * @param array<array-key, mixed> $preserved the other parameters of the address
     * @param array<string, mixed>    $state     what stays from one link to the next
     */
    public function __construct(
        private string $prefix,
        private array $preserved,
        private array $state,
    ) {
    }

    /**
     * @param array<string, mixed> $changes the parameters to change, an empty value removing one
     */
    public function href(array $changes = []): string
    {
        return '?'.http_build_query($this->query($changes), '', '&', \PHP_QUERY_RFC3986);
    }

    /**
     * The parameters a plain form carries in hidden fields, the others being its controls.
     *
     * @param list<string> $keys the state's keys to carry
     *
     * @return list<array{name: string, value: string}>
     */
    public function hiddenFields(array $keys): array
    {
        $state = array_intersect_key($this->state, array_flip($keys));
        $fields = [];

        foreach (explode('&', http_build_query($this->prefixed($state, $this->preserved))) as $pair) {
            if ('' !== $pair) {
                [$name, $value] = explode('=', $pair);
                $fields[] = ['name' => urldecode($name), 'value' => urldecode($value)];
            }
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $changes
     *
     * @return array<array-key, mixed>
     */
    private function query(array $changes): array
    {
        return $this->prefixed([...$this->state, ...$changes], $this->preserved);
    }

    /**
     * @param array<string, mixed>    $state
     * @param array<array-key, mixed> $query
     *
     * @return array<array-key, mixed>
     */
    private function prefixed(array $state, array $query): array
    {
        foreach ($state as $key => $value) {
            if (null !== $value && '' !== $value && [] !== $value) {
                $query[$this->prefix.$key] = $value;
            }
        }

        return $query;
    }
}
