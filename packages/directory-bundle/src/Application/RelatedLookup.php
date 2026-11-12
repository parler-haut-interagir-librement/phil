<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Application;

/**
 * Finds the listings that may point at one, so that the page of a listing reads only those among the published
 * listings of the other genre. `Infrastructure\Doctrine\DoctrineRelatedLookup` is the Doctrine adapter.
 */
interface RelatedLookup
{
    /**
     * The identifiers of the listings of a genre whose live rows name a listing under a relation, each once: every
     * listing published with that relation is among them, and the directory keeps those whose published row does.
     *
     * @return list<string>
     */
    public function pointingAt(string $genre, string $relation, string $resourceId): array;
}
