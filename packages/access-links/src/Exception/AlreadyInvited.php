<?php

declare(strict_types=1);

namespace Phil\AccessLinks\Exception;

use RuntimeException;

/**
 * A recipient invited twice to the same campaign: one invitation per recipient and campaign, whose link is issued again
 * with `reissue()`.
 */
final class AlreadyInvited extends RuntimeException implements AccessLinksException
{
    public static function to(string $campaign, string $recipient): self
    {
        return new self(\sprintf('The recipient "%s" is already invited to "%s": issue the link again instead.', $recipient, $campaign));
    }
}
