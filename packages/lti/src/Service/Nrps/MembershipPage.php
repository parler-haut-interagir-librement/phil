<?php

declare(strict_types=1);

namespace Phil\Lti\Service\Nrps;

use Phil\Lti\Message\Context;
use Phil\Url\Url;

/**
 * A page of the members of a context, the address of the next page when there is one, and the address of the changes
 * since this read, when the platform offers it.
 */
final readonly class MembershipPage
{
    /**
     * @param list<Member> $members
     */
    public function __construct(
        public Context $context,
        public array $members,
        public ?Url $next = null,
        public ?Url $differences = null,
    ) {
    }
}
