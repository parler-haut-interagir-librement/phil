<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Application\Event;

/**
 * An admin started viewing the site as a member, by their stable identifiers.
 */
final readonly class ViewAsStarted
{
    public function __construct(
        public string $admin,
        public string $member,
    ) {
    }
}
