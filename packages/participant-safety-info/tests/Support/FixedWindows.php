<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo\Tests\Support;

use Override;
use Phil\ParticipantSafetyInfo\AccessWindow;
use Phil\ParticipantSafetyInfo\Contract\AccessWindows;

final class FixedWindows implements AccessWindows
{
    /**
     * @param array<string, AccessWindow> $windows by event
     */
    public function __construct(
        public array $windows = [],
    ) {
    }

    #[Override]
    public function windowOf(string $event): ?AccessWindow
    {
        return $this->windows[$event] ?? null;
    }
}
