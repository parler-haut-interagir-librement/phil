<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application;

/**
 * What a moderator gives when their decision restricts the author: the reason, from the catalogue of the reports, the
 * facts the decision rests on, and — when the configured one does not fit — the provision of the terms or of the law.
 */
final readonly class Grounds
{
    public function __construct(
        public string $reason,
        public ?string $explanation,
        public ?string $reference = null,
    ) {
    }
}
