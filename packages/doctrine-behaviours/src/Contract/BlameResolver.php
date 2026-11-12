<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Contract;

/**
 * Who is acting now: the identifier of the user, or null when nobody is known — a command, a scheduled job.
 */
interface BlameResolver
{
    public function current(): ?string;
}
