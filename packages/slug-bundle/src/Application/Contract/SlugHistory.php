<?php

declare(strict_types=1);

namespace Phil\SlugBundle\Application\Contract;

use Phil\Slug\Slug;
use Phil\SlugBundle\Domain\Exception\InvalidSlugHistory;

/**
 * The former slugs of the subjects of a scope, each pointing to the subject's current slug, so that an address that
 * used an old slug can be answered with a permanent redirect.
 *
 * A **scope** is the space in which a slug is unique: `page`, `page:fr`, `tenant-42:product`. A **subject** is the
 * identifier of what the slug names in that scope, as the application writes it. The code that changes a slug records
 * the change; a redirect provider or a controller reads it. The history keeps no chain: every former slug of a subject
 * points to its newest slug, and a slug that becomes current again, for any subject, stops being a former one.
 */
interface SlugHistory
{
    /**
     * Records that the subject's slug changed from `$former` to `$current` in the scope. Recording a slug as its own
     * former slug changes nothing.
     *
     * @throws InvalidSlugHistory when the scope or the subject is not one the history can hold
     */
    public function record(string $scope, string $subject, Slug $former, Slug $current): void;

    /**
     * The current slug of the subject that once had `$former` in the scope, or `null` when `$former` is not a former
     * slug there.
     *
     * @throws InvalidSlugHistory when the scope is not one the history can hold
     */
    public function currentSlug(string $scope, Slug $former): ?Slug;

    /**
     * Every former slug of the scope with the current slug it points to, keyed by the former slug, in the order of
     * the former slugs.
     *
     * @return iterable<string, Slug>
     *
     * @throws InvalidSlugHistory when the scope is not one the history can hold
     */
    public function formerSlugs(string $scope): iterable;

    /**
     * Forgets every former slug of the subject in the scope: the subject was deleted, or its old addresses should no
     * longer answer.
     *
     * @throws InvalidSlugHistory when the scope or the subject is not one the history can hold
     */
    public function forget(string $scope, string $subject): void;
}
