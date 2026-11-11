<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Domain\Repository;

use Phil\ReferencesBundle\Domain\ValueObject\Usage;
use Phil\Resource\ResourceReference;

interface UsageRepository
{
    /**
     * Replaces everything a source uses with these usages.
     *
     * @param list<Usage> $usages
     */
    public function replace(ResourceReference $source, array $usages): void;

    /**
     * Forgets everything a source uses.
     */
    public function forget(ResourceReference $source): void;

    /**
     * Where a target is used, by source then field.
     *
     * @return list<Usage>
     */
    public function usagesOf(ResourceReference $target): array;

    /**
     * Where these targets are used, target after target in the order given, each by source then field: one read for
     * many targets.
     *
     * @param list<ResourceReference> $targets
     *
     * @return list<Usage>
     */
    public function usagesOfAll(array $targets): array;

    /**
     * Every target used, each once, by kind then identifier.
     *
     * @return iterable<ResourceReference>
     */
    public function targets(): iterable;
}
