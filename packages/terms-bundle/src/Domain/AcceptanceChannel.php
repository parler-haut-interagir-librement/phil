<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Domain;

/**
 * Where an acceptance was given: the signup form, the acceptance page the gate sends a member to, an API of the
 * application, or an import of records kept elsewhere.
 */
enum AcceptanceChannel: string
{
    case Signup = 'signup';

    case Form = 'form';

    case Api = 'api';

    case Import = 'import';
}
