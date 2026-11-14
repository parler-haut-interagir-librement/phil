<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Application\Contract;

/**
 * Who acts in the current unit of work, for the rows the bundle records itself — a workflow transition, an export.
 */
interface ActorResolver
{
    /**
     * @return string|null the stable identifier of the account or process, never an e-mail address nor a name; `null`
     *                     when nobody is known
     */
    public function current(): ?string;
}
