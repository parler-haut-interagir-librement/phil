<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Application\Contract;

/**
 * Every member of the application, by stable identifier: the members the list of who has not accepted, the notices
 * and the reminders go through.
 */
interface MemberSource
{
    /**
     * @return iterable<string>
     */
    public function members(): iterable;
}
