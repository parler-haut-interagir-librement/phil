<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Contract;

use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;

/**
 * The one root of each single kind of content, kept so that the store refuses a second one.
 * `Infrastructure\Doctrine\DoctrineSingleRootRepository` is the Doctrine adapter, on the table of `SingleRoot`.
 */
interface SingleRootRepository
{
    /**
     * Records `$root` as the root of its kind, in the caller's transaction, at once.
     *
     * @throws InvalidContent when the kind already has its root, recorded by another transaction meanwhile
     */
    public function claim(ContentReference $root): void;

    /**
     * Frees the kind of `$root` when `$root` is its recorded root; another root of the kind is left recorded.
     */
    public function release(ContentReference $root): void;
}
