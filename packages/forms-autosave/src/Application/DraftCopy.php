<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Application;

use DateTimeImmutable;
use Phil\FormsAutosave\Domain\ValueObject\DraftContent;

/**
 * A draft read from the server, opened: its revision, when it was saved, and its content.
 */
final readonly class DraftCopy
{
    public function __construct(
        public int $revision,
        public DateTimeImmutable $savedAt,
        public DraftContent $content,
    ) {
    }
}
