<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application;

/**
 * What a subject reference stands for when a notification is shown: its label, and the page it opens.
 */
final readonly class ResolvedSubject
{
    public function __construct(
        public string $label,
        public ?string $url = null,
    ) {
    }
}
