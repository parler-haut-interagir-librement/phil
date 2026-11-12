<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application;

/**
 * A column that holds a medium: the entity and its field, how many records hold it there, and what its deletion does
 * to them.
 */
final readonly class MediaReference
{
    /**
     * @param class-string $entity
     * @param int<0, max>  $count
     */
    public function __construct(
        public string $entity,
        public string $field,
        public int $count,
        public DeleteBehaviour $behaviour,
    ) {
    }

    public function place(): string
    {
        return \sprintf('%s.%s (%d)', $this->entity, $this->field, $this->count);
    }
}
