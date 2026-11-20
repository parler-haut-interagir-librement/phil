<?php

declare(strict_types=1);

namespace Phil\BrailleExportLiblouis\Exception;

/**
 * The program reports a version of liblouis older than the oldest the translator accepts, which leaves memory-safety
 * defects open to the texts of the documents it translates.
 */
final class OutdatedLiblouis extends LiblouisException
{
    public static function of(string $version, string $minimum): self
    {
        return new self(\sprintf('liblouis %s is older than %s, the oldest version accepted: update liblouis.', $version, $minimum), 0);
    }
}
