<?php

declare(strict_types=1);

namespace Phil\Lti\Platform\Nrps;

use Phil\Lti\Contract\Roster;
use Phil\Lti\Exception\AccessDenied;
use Phil\Lti\Message\Context;
use Phil\Lti\Platform\Token\AccessToken;
use Phil\Lti\Service\Nrps\Member;
use Phil\Lti\Service\Scope;

/**
 * The Names and Role Provisioning Service of the platform: a page of the members of a context for a tool granted the
 * scope, in a context where it is placed. The page holds `limit` members at most, 100 by default and 1,000 at most;
 * `nextOffset` is where the next page starts, null on the last.
 */
final readonly class NrpsService
{
    public const int DEFAULT_LIMIT = 100;

    public const int MAX_LIMIT = 1000;

    public function __construct(
        private Roster $roster,
    ) {
    }

    /**
     * @return array{context: Context, members: list<Member>, nextOffset: ?int}|null null when
     *                                                                               the context is not one of the tool
     *
     * @throws AccessDenied
     */
    public function page(AccessToken $token, string $contextId, ?string $role = null, ?int $limit = null, int $offset = 0): ?array
    {
        if (!$token->grants(Scope::MEMBERSHIP_READ_ONLY)) {
            throw AccessDenied::insufficientScope(Scope::MEMBERSHIP_READ_ONLY);
        }

        $context = $this->roster->context($token->clientId, $contextId);

        if (!$context instanceof Context) {
            return null;
        }

        $limit = max(1, min(self::MAX_LIMIT, $limit ?? self::DEFAULT_LIMIT));
        $offset = max(0, $offset);
        $members = $this->roster->members($contextId, $role, $limit + 1, $offset);
        $more = \count($members) > $limit;

        return ['context' => $context, 'members' => \array_slice($members, 0, $limit), 'nextOffset' => $more ? $offset + $limit : null];
    }
}
