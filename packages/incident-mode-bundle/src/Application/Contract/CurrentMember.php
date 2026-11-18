<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Application\Contract;

/**
 * The member of the current request, by the stable identifier the application gives its members — a UUID, an OIDC
 * `sub`, a member number —, or `null` for a visitor.
 *
 * The identifier is asked of `EstablishedMembers` at a checkpoint, and becomes the actor of what the member starts,
 * extends or lifts in the administration: an identifier that looks like an e-mail address is refused there.
 */
interface CurrentMember
{
    public function identifier(): ?string;
}
