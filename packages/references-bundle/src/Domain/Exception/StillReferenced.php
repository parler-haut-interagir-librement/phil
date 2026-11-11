<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Domain\Exception;

use Phil\ReferencesBundle\Domain\ValueObject\Usage;
use Phil\Resource\ResourceReference;
use RuntimeException;

/**
 * A resource that is still used cannot be deleted: the message names the first sources that use it.
 */
final class StillReferenced extends RuntimeException implements ReferencesException
{
    /**
     * @param non-empty-list<Usage> $usages
     */
    private function __construct(
        public readonly ResourceReference $target,
        public readonly array $usages,
    ) {
        $named = array_map(static fn (Usage $usage): string => $usage->source->toString().' ('.$usage->field.')', \array_slice($usages, 0, 3));
        $more = \count($usages) - \count($named);

        parent::__construct(\sprintf('%s is still used by %s%s.', $target->toString(), implode(', ', $named), $more > 0 ? \sprintf(' and %d more', $more) : ''));
    }

    /**
     * @param non-empty-list<Usage> $usages
     */
    public static function by(ResourceReference $target, array $usages): self
    {
        return new self($target, $usages);
    }
}
