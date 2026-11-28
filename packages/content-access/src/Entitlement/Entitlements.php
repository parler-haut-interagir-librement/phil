<?php

declare(strict_types=1);

namespace Phil\ContentAccess\Entitlement;

/**
 * Where the grants of a person come from: subscriptions, memberships, purchases, enrolments, donations or an external
 * platform, each behind its own implementation. Read only when a rule asks for a feature, never for a visitor who is
 * not signed in.
 *
 * An implementation keeps nothing from one request to the next, so that one instance serves every request of a worker.
 */
interface Entitlements
{
    /**
     * Every grant of the person, ended ones included or not: the library keeps only those active at the instant asked.
     *
     * @param string $subject the person's stable identifier, never an email address
     *
     * @return list<Grant>
     */
    public function grantsOf(string $subject): array;
}
