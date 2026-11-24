<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Render;

use Override;
use Phil\SeoCheck\Contract\ReportRenderer;
use Phil\SeoCheck\Message;
use Phil\SeoCheck\Report;

/**
 * The report as text, for a console or a log: a line that counts the points, then one line per point, its severity,
 * its rule and its message.
 *
 * ```text
 * 2 point(s) to correct.
 * [error] canonical: The canonical link names https://example.org/a, but the page was published at https://example.org/b.
 * [warning] image-alt: The image /logo.png has no alt attribute: describe it, or give it alt="" when it is only decoration.
 * ```
 */
final readonly class TextRenderer implements ReportRenderer
{
    #[Override]
    public function render(Report $report): string
    {
        if ($report->isEmpty()) {
            return $report->translate(new Message('report.clean'))."\n";
        }

        $lines = [$report->translate(new Message('report.count', ['count' => \count($report)]))];

        foreach ($report->findings as $finding) {
            $lines[] = \sprintf('[%s] %s: %s', $finding->severity->value, $finding->rule, $report->message($finding));
        }

        return implode("\n", $lines)."\n";
    }
}
