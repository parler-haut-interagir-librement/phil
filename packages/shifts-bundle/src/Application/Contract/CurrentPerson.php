<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Application\Contract;

/**
 * The person signed in, by the identifier the application gives them, or nobody, and the roles a shift may require of
 * them.
 */
interface CurrentPerson
{
    public function current(): ?string;

    /**
     * @return list<string>
     */
    public function roles(): array;
}
