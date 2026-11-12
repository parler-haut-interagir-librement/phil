<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Application\Contract;

/**
 * An index that applies synonyms itself, from a list it keeps per index, instead of reading the alternatives of a
 * query's terms: Meilisearch.
 */
interface SynonymAwareIndex
{
    /**
     * Replaces the synonyms of an index.
     *
     * @param array<string, list<string>> $synonyms each phrase and the others it also finds
     */
    public function replaceSynonyms(string $index, array $synonyms): void;
}
