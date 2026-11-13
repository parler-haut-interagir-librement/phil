<?php

declare(strict_types=1);

namespace Phil\AccessLinks\Exception;

use RuntimeException;

/**
 * An answer sent after the invitation closed: the link still shows the answer, and no longer changes it.
 */
final class InvitationClosed extends RuntimeException implements AccessLinksException
{
    public static function create(): self
    {
        return new self('The invitation is closed: its answer can no longer change.');
    }
}
