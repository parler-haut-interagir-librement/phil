<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Application\Exception;

use Phil\NewsletterArchive\Domain\Exception\NewsletterArchiveException;
use RuntimeException;

/**
 * A campaign the archive does not take: unknown, or not sent yet.
 */
final class NotArchivable extends RuntimeException implements NewsletterArchiveException
{
    public static function unknown(string $campaignId): self
    {
        return new self(\sprintf('No campaign "%s".', $campaignId));
    }

    public static function notSent(string $campaignId): self
    {
        return new self(\sprintf('The campaign "%s" is not sent: only a sent campaign is archived.', $campaignId));
    }

    public static function notArchived(string $campaignId): self
    {
        return new self(\sprintf('The campaign "%s" is not in the archive.', $campaignId));
    }
}
