<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Application;

use Override;
use Phil\SearchBundle\Application\Contract\RejectedForms;
use Phil\SearchBundle\Application\Contract\SynonymProvider;
use Phil\SearchBundle\Domain\Exception\InvalidSearch;
use Phil\SearchBundle\Domain\ValueObject\SynonymRule;

/**
 * The rejected forms of a vocabulary as one-way synonyms: a search for a rejected form finds it and its preferred term,
 * a search for the preferred term finds only that. A form that is no synonym — too long, the same as its term once
 * normalized — is left out.
 */
final readonly class RejectedFormSynonyms implements SynonymProvider
{
    public function __construct(
        private RejectedForms $forms,
    ) {
    }

    #[Override]
    public function rules(string $language): iterable
    {
        foreach ($this->forms->rejectedForms($language) as [$rejected, $preferred]) {
            try {
                yield SynonymRule::oneWay([$rejected], [$rejected, $preferred]);
            } catch (InvalidSearch) {
                continue;
            }
        }
    }
}
