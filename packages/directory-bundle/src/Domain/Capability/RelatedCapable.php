<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Domain\Capability;

/**
 * A row that holds the listings its listing is related to, by relation: `played_at` → the identifiers of the larp
 * sites a larp is played at. Shared by every locale.
 */
interface RelatedCapable
{
    /**
     * @return array<string, list<string>> listing identifiers by relation, no relation without one
     */
    public function getRelated(): array;

    /**
     * @param array<string, list<string>> $related
     */
    public function setRelated(array $related): void;
}
