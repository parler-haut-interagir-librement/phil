<?php

declare(strict_types=1);

namespace Phil\SearchSynonyms\Application\Contract;

use Phil\SearchSynonyms\Domain\ValueObject\SynonymList;

/**
 * Where the synonyms of each language are kept: files, memory, or a table of the application's.
 */
interface SynonymStore
{
    /**
     * The synonyms of a language, none when it has no list.
     *
     * @param string $language a language tag, read as its primary subtag
     */
    public function get(string $language): SynonymList;

    /**
     * Replaces the synonyms of the list's language.
     */
    public function save(SynonymList $list): void;

    /**
     * The languages that have a list, in alphabetical order.
     *
     * @return list<string>
     */
    public function languages(): array;
}
