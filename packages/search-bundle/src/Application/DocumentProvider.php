<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Application;

use Phil\SearchBundle\Domain\ValueObject\Document;

/**
 * Turns one kind of resource into the documents of an index, and gives them all when the index is rebuilt. The
 * application writes one per kind; the bundle finds them by autoconfiguration.
 */
interface DocumentProvider
{
    public function index(): string;

    public function supports(object $resource): bool;

    /**
     * The resource's document, or `null` when it must not be found — a draft, a private page.
     */
    public function document(object $resource): ?Document;

    /**
     * The resource's identifier in the index, to remove it.
     */
    public function id(object $resource): string;

    /**
     * Every resource of the index, to rebuild it.
     *
     * @return iterable<object>
     */
    public function all(): iterable;
}
