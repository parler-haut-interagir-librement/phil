<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Domain\Capability;

/**
 * @phpstan-require-implements RelatedCapable
 */
trait RelatedTrait
{
    /** @var array<string, list<string>>|null */
    private ?array $related = null;

    /**
     * @return array<string, list<string>>
     */
    public function getRelated(): array
    {
        return $this->related ?? [];
    }

    /**
     * @param array<string, list<string>> $related
     */
    public function setRelated(array $related): void
    {
        $related = array_filter($related, static fn (array $ids): bool => [] !== $ids);
        $this->related = [] === $related ? null : $related;
    }
}
