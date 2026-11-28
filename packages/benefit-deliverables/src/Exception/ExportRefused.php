<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables\Exception;

use RuntimeException;

/**
 * A shipping export that cannot be written or downloaded: a benefit that ships nothing, too many rows, a link that is
 * wrong or expired, a file already erased.
 */
final class ExportRefused extends RuntimeException
{
    public static function shipsNothing(string $benefit): self
    {
        return new self(\sprintf('The benefit "%s" ships nothing: it has no shipping export.', $benefit));
    }

    public static function tooManyRows(int $limit): self
    {
        return new self(\sprintf('A shipping export holds %d rows at most.', $limit));
    }

    /**
     * The same answer whatever the reason, so that a link tried by guess tells nothing.
     */
    public static function unavailable(): self
    {
        return new self('This export is not available: its link is wrong or expired.');
    }

    public static function storage(string $reason): self
    {
        return new self(\sprintf('The export file cannot be stored: %s', $reason));
    }
}
