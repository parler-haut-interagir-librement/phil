<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck;

/**
 * How much a finding matters, from the one that keeps people out to the one that only asks for a look.
 */
enum Severity: string
{
    /** Some readers cannot reach or understand the content: it is fixed before it is published. */
    case Error = 'error';

    /** The content is harder to use than it should be, or probably wrong: someone looks before publishing. */
    case Warning = 'warning';

    /** Worth knowing, perhaps intended: nothing has to change. */
    case Notice = 'notice';

    /**
     * Whether this severity is `$minimum` or graver: an error is at least a warning, a notice is only a notice.
     */
    public function isAtLeast(self $minimum): bool
    {
        return match ($minimum) {
            self::Notice => true,
            self::Warning => self::Notice !== $this,
            self::Error => self::Error === $this,
        };
    }
}
