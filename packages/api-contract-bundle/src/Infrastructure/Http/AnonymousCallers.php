<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\Http;

/**
 * What an `#[Idempotent]` endpoint does with a caller that is neither a user nor a client.
 */
enum AnonymousCallers: string
{
    /** Answered with a `403` problem: anonymous callers cannot be told apart, so they cannot have keys of their own. */
    case Refuse = 'refuse';

    /**
     * Scoped by the client's address: every anonymous caller behind one address — a corporate proxy, a carrier's NAT —
     * shares its keys, so that one of them can be answered with the response another one was given.
     */
    case ByIp = 'ip';
}
