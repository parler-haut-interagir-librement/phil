<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct\Event;

/**
 * A report was filed. It names the report and its scope only; a listener tells the responsible people who may handle it, never the people the report names.
 */
final readonly class ReportFiled
{
    public function __construct(
        public string $report,
        public string $scope,
    ) {
    }
}
