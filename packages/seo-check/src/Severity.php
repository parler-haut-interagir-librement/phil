<?php

declare(strict_types=1);

namespace Phil\SeoCheck;

/**
 * How much a finding matters to search engines. None of them stops a publication: the report is a list of points to
 * correct, read by whoever published the page.
 */
enum Severity: string
{
    /** Search engines misread the page, or cannot index it as it was published: correct it soon. */
    case Error = 'error';

    /** The page is indexed, but shown or reached worse than it could be. */
    case Warning = 'warning';

    /** Worth knowing, perhaps intended: nothing has to change. */
    case Info = 'info';

    /**
     * Whether this severity is `$minimum` or graver: an error is at least a warning, an information only an information.
     */
    public function isAtLeast(self $minimum): bool
    {
        return match ($minimum) {
            self::Info => true,
            self::Warning => self::Info !== $this,
            self::Error => self::Error === $this,
        };
    }
}
