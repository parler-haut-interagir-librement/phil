<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application\Contract;

/**
 * Implemented by the application's user class, for the default current member and moderator.
 *
 * **A dedicated method, because a user identifier is often an e-mail address**, and a member is designated by a
 * stable identifier: a UUID, an OIDC `sub`, a member number.
 */
interface ModerationMember
{
    public function moderationIdentifier(): string;
}
