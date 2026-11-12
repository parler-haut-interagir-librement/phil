<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Domain\Capability;

use Phil\Contact\ContactCard;

/**
 * @phpstan-require-implements ContactCapable
 */
trait ContactTrait
{
    private ?ContactCard $contact = null;

    public function getContact(): ?ContactCard
    {
        return $this->contact;
    }

    public function setContact(?ContactCard $contact): void
    {
        $this->contact = $contact;
    }
}
