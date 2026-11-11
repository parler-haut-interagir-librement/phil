<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Functional\App\Membership;

use DomainException;
use Phil\DddBundle\Domain\Exception\DomainError;

final class EmailAlreadyRegistered extends DomainException implements DomainError
{
    public static function for(string $email): self
    {
        return new self(\sprintf('The e-mail address "%s" is already registered.', $email));
    }
}
