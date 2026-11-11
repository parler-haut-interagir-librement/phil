<?php

declare(strict_types=1);

namespace Phil\Checkin\Exception;

use RuntimeException;

/**
 * The attendance of a credential kept changing under a scan: every attempt to record it met a scan of another device
 * recorded first. The scan can be sent again with the same identifier.
 */
final class LedgerContention extends RuntimeException implements CheckinException
{
    public static function after(int $attempts): self
    {
        return new self(\sprintf('The attendance of the credential changed under each of %d attempts to record the scan.', $attempts));
    }
}
