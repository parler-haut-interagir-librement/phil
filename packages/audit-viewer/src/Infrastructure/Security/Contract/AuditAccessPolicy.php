<?php

declare(strict_types=1);

namespace Phil\AuditViewer\Infrastructure\Security\Contract;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * What the application refuses to a holder of the viewer's roles: exports out of office hours, the history of an order
 * to the manager of another shop, the segments of another site. `AuditViewerVoter` asks it once the role holds, and
 * refuses what it refuses, whatever the access decision strategy: the policy narrows the roles, it never widens them.
 */
interface AuditAccessPolicy
{
    /**
     * @param string $attribute `PHIL_AUDIT_VIEW`, `PHIL_AUDIT_HISTORY` or `PHIL_AUDIT_EXPORT`
     * @param mixed  $subject   `null` for a page of the log or the export as a whole; the `AuditRow` for a row, for each
     *                          row a page of the log lists (`PHIL_AUDIT_VIEW`) and for each row the export writes
     *                          (`PHIL_AUDIT_EXPORT`); the segment's name for a verification and for each segment the
     *                          chain page lists; the `Subject` for a history
     */
    public function allows(string $attribute, mixed $subject, TokenInterface $token): bool;
}
