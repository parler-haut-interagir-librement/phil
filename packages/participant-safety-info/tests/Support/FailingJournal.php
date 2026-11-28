<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo\Tests\Support;

use Override;
use Phil\ParticipantSafetyInfo\AccessRecord;
use Phil\ParticipantSafetyInfo\Contract\AccessJournal;
use RuntimeException;

final class FailingJournal implements AccessJournal
{
    #[Override]
    public function record(AccessRecord $record): void
    {
        throw new RuntimeException('The journal is down.');
    }

    #[Override]
    public function recordsOf(string $event, string $participant, int $limit): array
    {
        return [];
    }
}
