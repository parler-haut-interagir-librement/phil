<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Render;

use Override;
use Phil\SeoCheck\Contract\ReportRenderer;
use Phil\SeoCheck\Report;

/**
 * The report as JSON, for an interface: `Report::toArray()`, its locale, the count of each severity and each point with
 * its message, its key and its placeholders, so that an interface may translate it again.
 */
final readonly class JsonRenderer implements ReportRenderer
{
    #[Override]
    public function render(Report $report): string
    {
        return json_encode($report->toArray(), \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
