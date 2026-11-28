<?php

declare(strict_types=1);

namespace Phil\CalendarImport;

use Phil\CalendarImport\State\ChangeReason;

/**
 * One event a read added, changed or removed, by its key: its draft, except when it was removed; why it changed; and
 * the fields an editor replaced that the source changed too — the editor's value stays, and is worth a look.
 */
final readonly class Change
{
    /**
     * @param list<Field> $conflicts
     */
    public function __construct(
        public ChangeKind $kind,
        public string $key,
        public ?ImportedDraft $draft = null,
        public ?ChangeReason $reason = null,
        public array $conflicts = [],
    ) {
    }
}
