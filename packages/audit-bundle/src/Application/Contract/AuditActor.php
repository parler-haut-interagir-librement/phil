<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Application\Contract;

/**
 * An account that can be named in the trail: the user class of the application implements it for the bundle's
 * `ActorResolver` on the Security component.
 */
interface AuditActor
{
    /**
     * @return string the account's stable identifier — an opaque identifier that never changes, never its e-mail
     *                address nor its name —: 1 to 128 letters, digits, ".", "_", ":" or "-"
     */
    public function auditActorId(): string;
}
