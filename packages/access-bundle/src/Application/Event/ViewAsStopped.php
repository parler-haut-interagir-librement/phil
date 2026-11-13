<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Application\Event;

/**
 * An admin stopped viewing the site as a member, by their stable identifiers.
 */
final readonly class ViewAsStopped
{
    public function __construct(
        public string $admin,
        public string $member,
    ) {
    }
}
