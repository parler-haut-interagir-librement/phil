<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Domain\Capability;

use Phil\Contact\ContactCard;

/**
 * A row that holds how to reach who it describes: a contact card of `phil/contact`, shared by every locale.
 */
interface ContactCapable
{
    public function getContact(): ?ContactCard;

    public function setContact(?ContactCard $contact): void;
}
