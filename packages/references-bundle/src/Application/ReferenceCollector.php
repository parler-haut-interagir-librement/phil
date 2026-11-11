<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Application;

use Phil\Resource\ResourceReference;

/**
 * Finds the links of one kind of source — in its JSON blocks, its polymorphic associations. The application writes
 * one per kind of source; the bundle finds them by autoconfiguration.
 */
interface ReferenceCollector
{
    public function supports(object $source): bool;

    public function sourceOf(object $source): ResourceReference;

    /**
     * @return iterable<Link>
     */
    public function collect(object $source): iterable;
}
