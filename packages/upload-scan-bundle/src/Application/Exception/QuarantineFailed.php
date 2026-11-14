<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Application\Exception;

use RuntimeException;

/**
 * An infected file the quarantine could not keep. The file is still refused.
 */
final class QuarantineFailed extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
