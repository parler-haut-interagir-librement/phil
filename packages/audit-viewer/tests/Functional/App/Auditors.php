<?php

declare(strict_types=1);

namespace Phil\AuditViewer\Tests\Functional\App;

use Override;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Four accounts: one that reads the log, one that also exports it, a clerk who reads the history of a record only, and
 * one that does none of it.
 *
 * @implements UserProviderInterface<Auditor>
 */
final class Auditors implements UserProviderInterface
{
    private const array ROLES = [
        'reader' => ['ROLE_AUDIT_VIEWER'],
        'exporter' => ['ROLE_AUDIT_VIEWER', 'ROLE_AUDIT_EXPORT'],
        'member' => ['ROLE_USER'],
        'clerk' => ['ROLE_CLERK'],
    ];

    public static function account(string $identifier): Auditor
    {
        return match ($identifier) {
            'reader', 'exporter', 'member', 'clerk' => new Auditor($identifier, self::ROLES[$identifier]),
            default => throw new UserNotFoundException(),
        };
    }

    #[Override]
    public function refreshUser(UserInterface $user): UserInterface
    {
        return self::account($user->getUserIdentifier());
    }

    #[Override]
    public function supportsClass(string $class): bool
    {
        return Auditor::class === $class;
    }

    #[Override]
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        return self::account($identifier);
    }
}
