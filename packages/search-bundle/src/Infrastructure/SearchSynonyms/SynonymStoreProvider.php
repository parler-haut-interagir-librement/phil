<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Infrastructure\SearchSynonyms;

use Override;
use Phil\SearchBundle\Application\Contract\SynonymProvider;
use Phil\SearchBundle\Domain\ValueObject\SynonymRule;
use Phil\SearchSynonyms\Application\Contract\SynonymStore;

/**
 * The synonyms a store of `phil/search-synonyms` keeps — files of the project, or the administration's table —, read
 * as the bundle's rules: an equivalent set stays equivalent, a one-way set stays one-way.
 */
final readonly class SynonymStoreProvider implements SynonymProvider
{
    public function __construct(
        private SynonymStore $store,
    ) {
    }

    #[Override]
    public function rules(string $language): iterable
    {
        foreach ($this->store->get($language)->sets as $set) {
            yield $set->equivalent ? SynonymRule::equivalent($set->from) : SynonymRule::oneWay($set->from, $set->to);
        }
    }
}
