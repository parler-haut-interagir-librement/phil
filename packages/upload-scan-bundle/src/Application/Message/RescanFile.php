<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Application\Message;

use InvalidArgumentException;

/**
 * Scans a file of a `RescanTarget` again, on a worker: the message carries the name of the target and the identifier
 * of the file, never the file nor anything about the person who sent it; the target reads the file itself.
 */
final readonly class RescanFile
{
    /** The longest name or identifier a message carries, in bytes. */
    public const int MAX_LENGTH = 255;

    public function __construct(
        public string $target,
        public string $fileId,
    ) {
        foreach (['target' => $target, 'file identifier' => $fileId] as $what => $value) {
            if ('' === $value || \strlen($value) > self::MAX_LENGTH) {
                throw new InvalidArgumentException(\sprintf('The %s of a rescan is 1 to %d bytes.', $what, self::MAX_LENGTH));
            }
        }
    }
}
