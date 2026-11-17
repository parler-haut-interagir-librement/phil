<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Application\Event;

use Phil\AccessBundle\Domain\Status\ViewAsEnd;

/**
 * An admin stopped viewing the site as a member, by their stable identifiers, and why: the admin stopped, lost their
 * right, or the member left the space. The admin is empty when nobody is signed in any more.
 */
final readonly class ViewAsStopped
{
    public function __construct(
        public string $admin,
        public string $member,
        public ViewAsEnd $reason = ViewAsEnd::Stopped,
    ) {
    }
}
