<?php

declare(strict_types=1);

namespace Phil\SocialCards\Preview;

/**
 * How much a finding of `TagCheck` matters.
 */
enum Severity: string
{
    /** A link to the page is shown wrong, or without its card. */
    case Error = 'error';

    /** A link to the page is shown, but less well than it could be. */
    case Warning = 'warning';
}
