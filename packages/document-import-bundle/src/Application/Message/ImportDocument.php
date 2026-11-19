<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Application\Message;

use InvalidArgumentException;

/**
 * Imports a file on a worker: the message carries the claim of the file in `PendingImports` and nothing else — never
 * the file, its name nor anything about the person who sent it.
 */
final readonly class ImportDocument
{
    /** The pattern of a claim: 1 to 255 letters, digits, ".", "_" or "-". */
    public const string CLAIM = '/^[A-Za-z0-9._-]{1,255}$/D';

    public function __construct(
        public string $claim,
    ) {
        if (1 !== preg_match(self::CLAIM, $claim)) {
            throw new InvalidArgumentException('The claim of an import is 1 to 255 letters, digits, ".", "_" or "-".');
        }
    }
}
