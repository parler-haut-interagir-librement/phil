<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Domain\ValueObject;

use Phil\SearchBundle\Domain\Exception\InvalidSearch;

/**
 * What is looked for: words, every one of which a document holds; filters, each a field and the value it must have —
 * or a list of values, any of which it may have —; the facets to count; the language the words are written in, for
 * their synonyms; a page of results.
 *
 * Once expanded by its synonyms, each word, or phrase, of the query is a term with its alternatives: a document holds
 * one of the alternatives of every term.
 */
final readonly class SearchQuery
{
    public const int MAX_FACETS = 20;

    public const int MAX_FILTER_VALUES = 100;

    public const int MAX_ALTERNATIVES = 32;

    /**
     * @param array<string, scalar|list<scalar>> $filters
     * @param list<string>                       $facets
     * @param list<list<string>>|null            $terms
     */
    private function __construct(
        public string $text,
        public array $filters,
        public int $limit,
        public int $offset,
        public array $facets,
        private ?string $language,
        private ?array $terms,
    ) {
    }

    /**
     * @param array<string, scalar|list<scalar>> $filters  a field and its value, or the values any of which it may have
     * @param list<string>                       $facets   the filter fields whose values are counted, 20 at most
     * @param string|null                        $language the language of the words, as `fr` or `fr-CA`; by default,
     *                                                     that of the filter `locale`
     */
    public static function of(string $text, array $filters = [], int $limit = 20, int $offset = 0, array $facets = [], ?string $language = null): self
    {
        if ($limit < 1 || $limit > 1000) {
            throw InvalidSearch::because(\sprintf('A page of results holds 1 to 1000 documents; %d asked.', $limit));
        }

        if ($offset < 0) {
            throw InvalidSearch::because(\sprintf('A page starts at 0 or after; %d asked.', $offset));
        }

        foreach ($filters as $field => $value) {
            self::checkField($field);

            if (\is_array($value) && ([] === $value || \count($value) > self::MAX_FILTER_VALUES)) {
                throw InvalidSearch::because(\sprintf('A filter is a value or a list of 1 to %d values; the filter "%s" holds %d.', self::MAX_FILTER_VALUES, $field, \count($value)));
            }
        }

        if (\count($facets) > self::MAX_FACETS) {
            throw InvalidSearch::because(\sprintf('A search counts %d facets at most; %d asked.', self::MAX_FACETS, \count($facets)));
        }

        foreach ($facets as $facet) {
            self::checkField($facet);
        }

        return new self(trim($text), $filters, $limit, $offset, array_values(array_unique($facets)), null === $language ? null : self::primary($language), null);
    }

    /**
     * The words looked for, lower-case, each once.
     *
     * @return list<string>
     */
    public function words(): array
    {
        return array_values(array_unique(Phrase::words($this->text)));
    }

    /**
     * The language of the words, its primary subtag in lower case — `fr` for `fr-CA` or `fr_CA` —: the one given, else
     * that of the filter `locale` when it is one value, else `null`.
     */
    public function language(): ?string
    {
        if (null !== $this->language) {
            return $this->language;
        }

        $locale = $this->filters['locale'] ?? null;

        return \is_string($locale) && 1 === preg_match(self::LANGUAGE, $locale) ? self::primary($locale) : null;
    }

    /**
     * Each term of the query and its alternatives, the term first: one word per term until synonyms expand them.
     *
     * @return list<list<string>>
     */
    public function terms(): array
    {
        return $this->terms ?? array_map(static fn (string $word): array => [$word], $this->words());
    }

    /**
     * The same query whose terms are these, each with its alternatives, normalized and each once.
     *
     * @param list<list<string>> $terms
     */
    public function withTerms(array $terms): self
    {
        $checked = [];

        foreach ($terms as $alternatives) {
            $normalized = array_values(array_unique(array_filter(array_map(Phrase::normalize(...), $alternatives), static fn (string $alternative): bool => '' !== $alternative)));

            if ([] === $normalized || \count($normalized) > self::MAX_ALTERNATIVES) {
                throw InvalidSearch::because(\sprintf('A term has 1 to %d alternatives; %d given.', self::MAX_ALTERNATIVES, \count($normalized)));
            }

            $checked[] = $normalized;
        }

        return new self($this->text, $this->filters, $this->limit, $this->offset, $this->facets, $this->language, $checked);
    }

    /**
     * The filters but that of one field: those among which the values of its facet are counted.
     *
     * @return array<string, scalar|list<scalar>>
     */
    public function filtersWithout(string $field): array
    {
        $filters = $this->filters;
        unset($filters[$field]);

        return $filters;
    }

    private const string LANGUAGE = '/^([A-Za-z]{2,3})(?:[-_][A-Za-z0-9]{1,8})*$/D';

    private static function checkField(string $field): void
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $field)) {
            throw InvalidSearch::because(\sprintf('A field is 1 to 64 lower-case letters, digits or "_", starting with a letter; "%s" is not.', $field));
        }
    }

    private static function primary(string $language): string
    {
        if (1 !== preg_match(self::LANGUAGE, $language, $match)) {
            throw InvalidSearch::because(\sprintf('A language is a tag such as "fr" or "fr-CA"; "%s" is not.', $language));
        }

        return strtolower($match[1]);
    }
}
