<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Application;

use Phil\SearchBundle\Application\Contract\SynonymProvider;
use Phil\SearchBundle\Domain\ValueObject\Phrase;
use Phil\SearchBundle\Domain\ValueObject\SearchQuery;
use Phil\SearchBundle\Domain\ValueObject\SynonymRule;

/**
 * The synonyms of every provider, per language, applied to the words of a query: the longest phrase of the query a
 * rule knows, from the left, becomes a term with its alternatives — every phrase of an equivalent rule, the right side
 * of a one-way rule —, then the next word, until the end.
 *
 * The synonyms of a language are read from the providers once and kept until the container is reset, between two
 * requests of a worker; a term keeps 32 alternatives at most, the first ones read.
 */
final class SynonymExpander
{
    /**
     * Each language read, and its phrases with their alternatives.
     *
     * @var array<string, array<string, list<string>>>
     */
    private array $maps = [];

    /**
     * The most words of a phrase, per language read.
     *
     * @var array<string, int>
     */
    private array $longest = [];

    /**
     * @param iterable<SynonymProvider> $providers
     */
    public function __construct(
        private readonly iterable $providers,
    ) {
    }

    /**
     * The query with its terms expanded in its language; the query itself when it has no language or no word a rule
     * knows.
     */
    public function expand(SearchQuery $query): SearchQuery
    {
        $language = $query->language();

        if (null === $language) {
            return $query;
        }

        $map = $this->map($language);
        $words = $query->words();
        $count = \count($words);
        $terms = [];
        $expanded = false;

        $start = 0;

        while ($start < $count) {
            $taken = 1;
            $alternatives = [$words[$start]];

            for ($length = min($this->longest[$language], $count - $start); $length > 0; --$length) {
                $candidate = implode(' ', \array_slice($words, $start, $length));

                if (isset($map[$candidate])) {
                    [$taken, $alternatives, $expanded] = [$length, $map[$candidate], true];

                    break;
                }
            }

            $terms[] = $alternatives;
            $start += $taken;
        }

        return $expanded ? $query->withTerms($terms) : $query;
    }

    /**
     * The phrases of a language with the alternatives each is searched as.
     *
     * @return array<string, list<string>>
     */
    public function map(string $language): array
    {
        if (isset($this->maps[$language])) {
            return $this->maps[$language];
        }

        $map = [];
        $longest = 0;

        foreach ($this->providers as $provider) {
            foreach ($provider->rules($language) as $rule) {
                foreach ($rule->from as $phrase) {
                    $map[$phrase] = \array_slice(array_unique([...$map[$phrase] ?? [], ...$this->alternatives($rule, $phrase)]), 0, SearchQuery::MAX_ALTERNATIVES);
                    $longest = max($longest, \count(Phrase::words($phrase)));
                }
            }
        }

        $this->longest[$language] = $longest;

        return $this->maps[$language] = $map;
    }

    /**
     * What every phrase of a language also finds, itself left out: the list of an engine that keeps synonyms itself.
     *
     * @param list<string> $languages
     *
     * @return array<string, list<string>>
     */
    public function engineSynonyms(array $languages): array
    {
        $synonyms = [];

        foreach ($languages as $language) {
            foreach ($this->map($language) as $phrase => $alternatives) {
                $others = array_diff($alternatives, [$phrase]);

                if ([] !== $others) {
                    $synonyms[$phrase] = array_values(array_unique([...$synonyms[$phrase] ?? [], ...$others]));
                }
            }
        }

        return $synonyms;
    }

    public function reset(): void
    {
        $this->maps = [];
        $this->longest = [];
    }

    /**
     * The phrase first, then the others: the phrase comes once, whatever its place in the rule, when the map keeps each
     * alternative once.
     *
     * @return list<string>
     */
    private function alternatives(SynonymRule $rule, string $phrase): array
    {
        return $rule->equivalent ? [$phrase, ...$rule->to] : $rule->to;
    }
}
