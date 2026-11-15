<?php

declare(strict_types=1);

namespace App\Terms;

use Phil\TermsBundle\Application\Contract\MemberSource;

/**
 * Every member of the application, by stable identifier, as phil/terms-bundle reads them for the list of who has not
 * accepted and for the reminders. Until it reads the application's accounts it lists nobody.
 */
final readonly class Members implements MemberSource
{
    /**
     * @return iterable<string>
     */
    public function members(): iterable
    {
        return [];
    }
}
