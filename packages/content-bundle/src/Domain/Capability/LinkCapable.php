<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Capability;

use Phil\ContentBundle\Domain\ValueObject\Link;

/**
 * A row that points somewhere: another content root, or an absolute URL (`Link`).
 *
 * What the link is for — a menu entry, a redirect, a "read more" — is the application's. `LinkDataMapper`
 * writes it on the localized row, so a language can point to its own page.
 *
 * Use `LinkTrait` for the implementation.
 */
interface LinkCapable
{
    public function getLink(): ?Link;

    public function setLink(?Link $link): void;
}
