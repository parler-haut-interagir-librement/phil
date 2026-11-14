<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Infrastructure\Export;

use Override;
use Phil\AuditBundle\Application\Exception\ExportFailed;
use Phil\AuditBundle\Domain\Contract\FactExporter;
use Phil\AuditBundle\Domain\Export\ExportedFact;

/**
 * Facts appended to a file, one JSON object per line, each batch in one locked write: what a log shipper tails and
 * sends on to a dashboard. A `php://` stream — `php://stdout` for a container's log — is written without the lock,
 * which only a file takes.
 */
final readonly class JsonLinesFactExporter implements FactExporter
{
    public function __construct(
        private string $file,
    ) {
    }

    #[Override]
    public function export(array $facts): void
    {
        $lines = '';

        foreach ($facts as $fact) {
            $lines .= self::line($fact)."\n";
        }

        $flags = str_starts_with($this->file, 'php://') ? \FILE_APPEND : \FILE_APPEND | \LOCK_EX;

        if (false === @file_put_contents($this->file, $lines, $flags)) {
            throw ExportFailed::because(\sprintf('%d fact(s) could not be appended to %s.', \count($facts), $this->file));
        }
    }

    /**
     * One fact as the line it is written as, without its line break.
     */
    public static function line(ExportedFact $fact): string
    {
        return json_encode($fact->toArray(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }
}
