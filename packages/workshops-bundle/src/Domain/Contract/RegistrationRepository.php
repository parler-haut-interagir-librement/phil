<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Domain\Contract;

use Closure;
use Phil\WorkshopsBundle\Domain\Model\Registration;
use Phil\WorkshopsBundle\Domain\Model\WorkshopSession;
use Phil\WorkshopsBundle\Domain\Status\RegistrationStatus;

/**
 * Where the registrations are kept, and the lock that makes the requests on one session run one at a time.
 */
interface RegistrationRepository
{
    public function get(string $id): ?Registration;

    public function save(Registration $registration): void;

    /**
     * A participant's confirmed registration in a session, if any.
     */
    public function live(WorkshopSession $session, string $participant): ?Registration;

    /**
     * The seats of a session's registrations with a status, added up.
     */
    public function seats(WorkshopSession $session, RegistrationStatus $status): int;

    /**
     * A session's registrations, of one status or all, at most `$limit`.
     *
     * @return list<Registration> in the order they were made
     */
    public function ofSession(WorkshopSession $session, int $limit, ?RegistrationStatus $status = null): array;

    /**
     * Runs the work in a transaction that holds the session's row, the session read again from it: two requests on
     * one session run one after the other, so that both cannot take the last seat.
     *
     * @template T
     *
     * @param Closure(): T $work
     *
     * @return T
     */
    public function exclusively(WorkshopSession $session, Closure $work): mixed;
}
