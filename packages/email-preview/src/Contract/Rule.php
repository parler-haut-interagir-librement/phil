<?php

declare(strict_types=1);

namespace Phil\EmailPreview\Contract;

use Phil\EmailPreview\CheckedEmail;
use Phil\EmailPreview\Finding;

/**
 * One check of an email, run on each preview: what it finds, nothing when the email follows it. A rule keeps no state
 * between two emails.
 */
interface Rule
{
    /**
     * The name of the rule, in kebab case, which each of its findings carries.
     */
    public function name(): string;

    /**
     * What the rule finds in the email, in the order of the email.
     *
     * @return iterable<Finding>
     */
    public function check(CheckedEmail $email): iterable;
}
