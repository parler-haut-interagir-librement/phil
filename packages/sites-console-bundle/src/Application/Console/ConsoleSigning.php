<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\Console;

use Phil\SitesConsoleBundle\Domain\Contract\KeyRotationRepository;
use Phil\SitesConsoleBundle\Domain\Model\KeyRotation;
use Phil\SitesConsoleBundle\Domain\Signature\SigningKey;

/**
 * The keys the console signs with: the current key, and the next one from the moment a rotation to it has reached
 * every site. Which one signs is read for each request, so that every process of the console switches together.
 */
final readonly class ConsoleSigning
{
    public function __construct(
        private SigningKey $current,
        private ?SigningKey $next = null,
        private ?KeyRotationRepository $rotations = null,
    ) {
    }

    public function current(): SigningKey
    {
        return $this->current;
    }

    public function next(): ?SigningKey
    {
        return $this->next;
    }

    /**
     * The key the next request is signed with.
     */
    public function key(): SigningKey
    {
        if (!$this->next instanceof SigningKey || !$this->rotations instanceof KeyRotationRepository) {
            return $this->current;
        }

        $rotation = $this->rotations->latestTo($this->next->verifyingKey()->toBase64());

        return $rotation instanceof KeyRotation && $rotation->phase()->signsWithNext() ? $this->next : $this->current;
    }
}
