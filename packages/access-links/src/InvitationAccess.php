<?php

declare(strict_types=1);

namespace Phil\AccessLinks;

/**
 * What a link opened at a moment: its invitation, and whether the answer can still change then.
 */
final readonly class InvitationAccess
{
    public function __construct(
        public Invitation $invitation,
        public bool $editable,
    ) {
    }
}
