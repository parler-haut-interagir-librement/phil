<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Publication;

use DateTimeImmutable;

/**
 * A transition of the `phil_content_publication` workflow planned for a moment: `publish` or `unpublish`.
 */
final readonly class ScheduledPublication
{
    public const string PUBLISH = 'publish';

    public const string UNPUBLISH = 'unpublish';

    public function __construct(
        public string $transition,
        public DateTimeImmutable $dueAt,
    ) {
    }
}
