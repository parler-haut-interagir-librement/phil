<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Application;

use Phil\SearchBundle\Domain\ValueObject\SearchQuery;

/**
 * What a visitor asked a results page, read from its query string: the words, `q`; the values chosen for each facet,
 * `filter[field][]`; the page, `page`. Anything else is left out — a field that is no facet of the page, a value that
 * is not text, a page that is no number —, so that a link copied, cut or forged still opens a page.
 *
 * Each filter removed, one at a time or all together, is a link to the same page with a shorter query string: it works
 * without JavaScript.
 */
final readonly class SearchRequest
{
    public const int MAX_TEXT = 200;

    public const int MAX_VALUE = 200;

    public const int MAX_PAGE = 1000;

    /**
     * @param array<string, list<string>> $selection
     */
    private function __construct(
        public string $text,
        public array $selection,
        public int $page,
        public int $perPage,
    ) {
    }

    /**
     * @param array<array-key, mixed> $parameters the query string, as `$request->query->all()`
     * @param list<string>            $facets     the fields a visitor may filter on
     * @param int                     $perPage    the results of a page, 1 to 1000
     */
    public static function fromQuery(array $parameters, array $facets, int $perPage = 20): self
    {
        $perPage = max(1, min(1000, $perPage));
        $text = \is_string($parameters['q'] ?? null) && 1 === preg_match('//u', $parameters['q']) ? trim(mb_substr($parameters['q'], 0, self::MAX_TEXT)) : '';
        $page = \is_string($parameters['page'] ?? null) && 1 === preg_match('/^[1-9]\d{0,3}$/D', $parameters['page']) ? min((int) $parameters['page'], self::MAX_PAGE) : 1;
        $asked = \is_array($parameters['filter'] ?? null) ? $parameters['filter'] : [];
        $selection = [];

        foreach ($facets as $facet) {
            $values = $asked[$facet] ?? [];

            foreach (\is_array($values) ? $values : [$values] as $value) {
                if (\is_string($value) && '' !== $value && mb_strlen($value) <= self::MAX_VALUE && 1 === preg_match('//u', $value) && \count($selection[$facet] ?? []) < SearchQuery::MAX_FILTER_VALUES && !\in_array($value, $selection[$facet] ?? [], true)) {
                    $selection[$facet][] = $value;
                }
            }
        }

        return new self($text, $selection, $page, $perPage);
    }

    /**
     * The query of this page: the words, the values chosen, any of which a document may have for a field, and the
     * filters the page sets itself — a locale, a kind —; every facet counted.
     *
     * @param list<string>          $facets  the facets to count
     * @param array<string, scalar> $filters the filters of the page, which a visitor does not choose
     */
    public function query(array $facets, array $filters = [], ?string $language = null): SearchQuery
    {
        return SearchQuery::of($this->text, [...$filters, ...$this->selection], $this->perPage, ($this->page - 1) * $this->perPage, $facets, $language);
    }

    public function isSelected(string $facet, string $value): bool
    {
        return \in_array($value, $this->selection[$facet] ?? [], true);
    }

    public function hasFilters(): bool
    {
        return [] !== $this->selection;
    }

    /**
     * The values chosen, each with its facet, in the order of the facets.
     *
     * @return list<array{facet: string, value: string}>
     */
    public function selected(): array
    {
        $selected = [];

        foreach ($this->selection as $facet => $values) {
            foreach ($values as $value) {
                $selected[] = ['facet' => $facet, 'value' => $value];
            }
        }

        return $selected;
    }

    /**
     * The same request without one value: back to the first page.
     */
    public function without(string $facet, string $value): self
    {
        $selection = $this->selection;
        $selection[$facet] = array_values(array_diff($selection[$facet] ?? [], [$value]));

        if ([] === $selection[$facet]) {
            unset($selection[$facet]);
        }

        return new self($this->text, $selection, 1, $this->perPage);
    }

    /**
     * The same words without any filter: back to the first page.
     */
    public function withoutFilters(): self
    {
        return new self($this->text, [], 1, $this->perPage);
    }

    /**
     * The same request on another page.
     */
    public function onPage(int $page): self
    {
        return new self($this->text, $this->selection, max(1, min($page, self::MAX_PAGE)), $this->perPage);
    }

    /**
     * The query string of this request, `?` and its parameters, or `?` alone: a link to the same page that keeps its
     * path, without reading any address.
     */
    public function queryString(): string
    {
        $parameters = [];

        if ('' !== $this->text) {
            $parameters['q'] = $this->text;
        }

        if ([] !== $this->selection) {
            $parameters['filter'] = $this->selection;
        }

        if ($this->page > 1) {
            $parameters['page'] = $this->page;
        }

        return '?'.http_build_query($parameters, '', '&', \PHP_QUERY_RFC3986);
    }
}
