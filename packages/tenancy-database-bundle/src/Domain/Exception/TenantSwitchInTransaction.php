<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Domain\Exception;

use LogicException;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;

/**
 * A tenant entered while a transaction is open on a connection that would have to move to another database: the
 * transaction belongs to the database it started on, so the switch is refused rather than committing half of the work
 * in one database and the rest in another.
 */
final class TenantSwitchInTransaction extends LogicException implements DomainError
{
    public static function between(Placement $from, Placement $to, TenantId $tenant): self
    {
        return new self(\sprintf('The tenant "%s" lives in the database "%s", but a transaction is open in "%s": commit or roll it back before entering the tenant.', $tenant->value, $to->toString(), $from->toString()));
    }
}
