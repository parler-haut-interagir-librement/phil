<?php

declare(strict_types=1);

namespace App\Terms;

use Phil\TermsBundle\Application\Contract\MemberResolver;

/**
 * The stable identifier of the signed-in member, as phil/terms-bundle names them: never an email address. Until it
 * reads the application's user it names nobody, and the gate asks no one to accept.
 */
final readonly class CurrentMember implements MemberResolver
{
    public function current(): ?string
    {
        return null;
    }
}
