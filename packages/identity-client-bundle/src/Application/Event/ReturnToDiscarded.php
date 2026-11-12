<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Application\Event;

/**
 * A `return_to` was refused and the sign-in will land on the default target. The refused value is not carried: it is
 * whatever the visitor sent.
 */
final readonly class ReturnToDiscarded
{
}
