<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Application\Exception;

use LogicException;

/**
 * A form or a constraint names a list the configuration's `lists` does not hold.
 */
final class UnknownEmailList extends LogicException
{
    /**
     * @param list<string> $known
     */
    public function __construct(string $list, array $known)
    {
        parent::__construct(\sprintf('The email list "%s" is not configured under phil_email_address.lists; the lists are: %s.', $list, [] === $known ? 'none' : implode(', ', $known)));
    }
}
