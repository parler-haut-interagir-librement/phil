<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Application\Contract;

/**
 * The forms a vocabulary rejects for its preferred terms in one language — `D&D` for `Donjons et Dragons`, an old
 * spelling, a merged tag —: a search for a rejected form also finds its preferred term. `RejectedFormSynonyms` turns
 * them into synonyms.
 */
interface RejectedForms
{
    /**
     * @param string $language a primary language subtag in lower case: `fr`, `en`
     *
     * @return iterable<array{string, string}> each rejected form and its preferred term
     */
    public function rejectedForms(string $language): iterable;
}
