<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Infrastructure\Audit;

use DateTimeImmutable;
use Override;
use Phil\AccessibilityStatement\Audit\AuditedPage;
use Phil\AccessibilityStatement\Audit\AuditResult;
use Phil\AccessibilityStatement\Audit\ReportReader;
use Phil\AccessibilityStatement\Contract\AuditSource;
use Phil\AccessibilityStatement\EvaluationMethod;
use Phil\AccessibilityStatement\Exception\InvalidReport;
use SplFileInfo;

/**
 * The latest audit as a directory of reports: each `<page>.json` file holds the JSON report phil/accessibility-check
 * wrote for one page, such as `phil:accessibility:check templates/home.html --format=json > var/accessibility/home.json`.
 * The audit is dated by the newest file. Files whose names start with a dot, other files and subdirectories are left
 * alone; a directory of more than `$maxFiles` reports, or a report larger than `$maxBytes`, is refused before it is
 * read.
 */
final readonly class ReportDirectory implements AuditSource
{
    public const int MAX_FILES = 1_000;

    public function __construct(
        private string $directory,
        private ReportReader $reader = new ReportReader(),
        private EvaluationMethod $method = EvaluationMethod::Automated,
        private ?string $reportUrl = null,
        private int $maxFiles = self::MAX_FILES,
        private int $maxBytes = ReportReader::MAX_BYTES,
    ) {
    }

    #[Override]
    public function latest(): ?AuditResult
    {
        $names = is_dir($this->directory) ? scandir($this->directory) : false;
        $files = array_filter(
            false === $names ? [] : $names,
            fn (string $name): bool => !str_starts_with($name, '.') && str_ends_with($name, '.json') && is_file($this->directory.'/'.$name),
        );

        if ([] === $files) {
            return null;
        }

        if (\count($files) > $this->maxFiles) {
            throw InvalidReport::because(\sprintf('An audit directory holds at most %d reports.', $this->maxFiles));
        }

        $pages = [];
        $moments = [];

        foreach ($files as $file) {
            $info = new SplFileInfo($this->directory.'/'.$file);

            if ($info->getSize() > $this->maxBytes) {
                throw InvalidReport::because(\sprintf('A report of an audit directory holds at most %d bytes: %s is larger.', $this->maxBytes, $file));
            }

            $moments[] = $info->getMTime();
            $pages[] = new AuditedPage(substr($file, 0, -5), $this->reader->fromJson((string) file_get_contents($info->getPathname())));
        }

        return new AuditResult(new DateTimeImmutable('@'.max($moments)), $pages, $this->method, $this->reportUrl);
    }
}
