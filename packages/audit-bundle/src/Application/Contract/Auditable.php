<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Application\Contract;

use Phil\AuditBundle\Domain\Chain\Subject;

/**
 * A record whose changes of state are audited: the subject of a workflow implements it, and each transition it goes
 * through is recorded about the subject it names.
 */
interface Auditable
{
    public function auditSubject(): Subject;
}
