<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Application\Contract;

/**
 * The identifier of the member making the request, stable and free of personal data, or null for a visitor.
 */
interface CurrentMember
{
    public function current(): ?string;
}
