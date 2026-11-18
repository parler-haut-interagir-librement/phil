<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application\Exception;

use Phil\OrganizationsBundle\Domain\Exception\OrganizationsException;
use RuntimeException;

/**
 * The invitation cannot be used: unknown, expired, revoked, used up, of an archived organization, or its creator may no
 * longer grant its roles. The message is the same for each, so that it tells nothing of a token; `$reason` tells the
 * application which.
 */
final class InvitationNotUsable extends RuntimeException implements OrganizationsException
{
    public const string UNKNOWN = 'unknown';

    public const string EXPIRED = 'expired';

    public const string REVOKED = 'revoked';

    public const string USED_UP = 'used_up';

    public const string ARCHIVED = 'archived';

    public const string CREATOR = 'creator';

    public function __construct(
        public readonly string $reason,
    ) {
        parent::__construct('This invitation cannot be used.');
    }

    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
