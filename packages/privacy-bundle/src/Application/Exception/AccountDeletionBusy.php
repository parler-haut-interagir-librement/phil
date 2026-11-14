<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Exception;

use Phil\PrivacyBundle\Domain\Exception\PrivacyError;
use RuntimeException;

/**
 * Another process holds the lock of this person: nothing was changed, and the person can try again.
 */
final class AccountDeletionBusy extends RuntimeException implements PrivacyError
{
    public static function create(): self
    {
        return new self('An account deletion of this person is being processed.');
    }
}
