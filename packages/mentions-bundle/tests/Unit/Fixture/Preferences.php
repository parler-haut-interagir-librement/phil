<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Unit\Fixture;

use Override;
use Phil\MentionsBundle\Application\Contract\MentionPreferences;

/**
 * Every member notified but those of `$silent`.
 */
final readonly class Preferences implements MentionPreferences
{
    /**
     * @param list<string> $silent
     */
    public function __construct(
        private array $silent = [],
    ) {
    }

    #[Override]
    public function notifies(string $recipient): bool
    {
        return !\in_array($recipient, $this->silent, true);
    }
}
