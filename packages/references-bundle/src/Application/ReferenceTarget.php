<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Application;

use Phil\Resource\ResourceReference;

/**
 * One kind of resource others may use: whether one still exists, and which reference an object is. The application
 * writes one per kind; the bundle finds them by autoconfiguration.
 */
interface ReferenceTarget
{
    public function kind(): string;

    public function exists(string $id): bool;

    /**
     * The reference of an object of this kind, or `null` when it is another kind.
     */
    public function referenceOf(object $resource): ?ResourceReference;
}
