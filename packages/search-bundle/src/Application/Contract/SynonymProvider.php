<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Application\Contract;

use Phil\SearchBundle\Domain\ValueObject\SynonymRule;

/**
 * Gives the synonyms of a language: a list the site keeps — `phil/search-synonyms` —, the forms a taxonomy rejected
 * for its preferred terms, or a source of the application's. A service implementing it is tagged
 * `phil_search.synonym_provider` by autoconfiguration, and the synonyms of every provider are read together.
 */
interface SynonymProvider
{
    /**
     * @param string $language a primary language subtag in lower case: `fr`, `en`
     *
     * @return iterable<SynonymRule>
     */
    public function rules(string $language): iterable;
}
