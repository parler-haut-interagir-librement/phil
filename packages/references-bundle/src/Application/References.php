<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Application;

use Phil\ReferencesBundle\Domain\Exception\StillReferenced;
use Phil\ReferencesBundle\Domain\Repository\UsageRepository;
use Phil\ReferencesBundle\Domain\ValueObject\Usage;
use Phil\Resource\ResourceReference;

/**
 * Where resources are used: what each source links to, kept up to date by its collector; whether a resource may be
 * deleted; which links point to resources that are gone.
 */
final readonly class References
{
    /**
     * @param iterable<ReferenceCollector> $collectors
     * @param iterable<ReferenceTarget>    $targets
     */
    public function __construct(
        private UsageRepository $usages,
        private iterable $collectors,
        private iterable $targets,
    ) {
    }

    /**
     * Records what a source links to now; returns `false` when no collector knows it.
     */
    public function refresh(object $source): bool
    {
        $collector = $this->collectorOf($source);

        if (!$collector instanceof ReferenceCollector) {
            return false;
        }

        $reference = $collector->sourceOf($source);
        $this->usages->replace($reference, array_map(static fn (Link $link): Usage => Usage::of($reference, $link->field, $link->target), iterator_to_array($collector->collect($source), false)));

        return true;
    }

    /**
     * Forgets what a source links to, when it is deleted; returns `false` when no collector knows it.
     */
    public function forget(object $source): bool
    {
        $collector = $this->collectorOf($source);

        if (!$collector instanceof ReferenceCollector) {
            return false;
        }

        $this->forgetSource($collector->sourceOf($source));

        return true;
    }

    /**
     * Forgets what a source links to by its reference, read while its identifier was known.
     */
    public function forgetSource(ResourceReference $source): void
    {
        $this->usages->forget($source);
    }

    /**
     * The reference of a source some collector knows, or `null`.
     */
    public function sourceOf(object $source): ?ResourceReference
    {
        return $this->collectorOf($source)?->sourceOf($source);
    }

    /**
     * @return list<Usage>
     */
    public function usagesOf(ResourceReference $target): array
    {
        return $this->usages->usagesOf($target);
    }

    /**
     * The reference of a resource others may use, or `null`.
     */
    public function referenceOf(object $resource): ?ResourceReference
    {
        foreach ($this->targets as $target) {
            $reference = $target->referenceOf($resource);

            if ($reference instanceof ResourceReference) {
                return $reference;
            }
        }

        return null;
    }

    /**
     * Refuses the deletion of a resource still used, by any source but those going away with it.
     *
     * @param array<array-key, ResourceReference> $leaving sources deleted at the same time
     *
     * @throws StillReferenced
     */
    public function assertUnused(ResourceReference $target, array $leaving = []): void
    {
        $gone = array_flip(array_map(static fn (ResourceReference $source): string => $source->toString(), $leaving));

        $usages = array_values(array_filter($this->usages->usagesOf($target), static fn (Usage $usage): bool => !isset($gone[$usage->source->toString()])));

        if ([] !== $usages) {
            throw StillReferenced::by($target, $usages);
        }
    }

    /**
     * The usages whose target is gone, for the kinds a `ReferenceTarget` knows.
     *
     * @return list<Usage>
     */
    public function broken(): array
    {
        $gone = [];

        foreach ($this->usages->targets() as $target) {
            $kind = $this->targetOfKind($target->key);

            if ($kind instanceof ReferenceTarget && !$kind->exists($target->id)) {
                $gone[] = $target;
            }
        }

        // Their usages are read once, for every target gone.
        return [] === $gone ? [] : $this->usages->usagesOfAll($gone);
    }

    private function collectorOf(object $source): ?ReferenceCollector
    {
        foreach ($this->collectors as $collector) {
            if ($collector->supports($source)) {
                return $collector;
            }
        }

        return null;
    }

    private function targetOfKind(string $kind): ?ReferenceTarget
    {
        foreach ($this->targets as $target) {
            if ($target->kind() === $kind) {
                return $target;
            }
        }

        return null;
    }
}
