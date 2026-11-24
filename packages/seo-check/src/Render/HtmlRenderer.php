<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Render;

use Override;
use Phil\SeoCheck\Contract\ReportRenderer;
use Phil\SeoCheck\Finding;
use Phil\SeoCheck\Message;
use Phil\SeoCheck\Report;

/**
 * The report as a fragment of HTML, for an administration: a section with a heading, the count of points, and a list
 * with one item per point, its severity as a class — `isError`, `isWarning`, `isInfo` — and as text, its message and
 * what it is about. Every text is escaped; the fragment holds no style and no script.
 */
final readonly class HtmlRenderer implements ReportRenderer
{
    #[Override]
    public function render(Report $report): string
    {
        $html = '<section class="seo-check-report" lang="'.$this->escape(str_replace('_', '-', $report->locale)).'">'
            .'<h2>'.$this->escape($report->translate(new Message('report.title'))).'</h2>';

        if ($report->isEmpty()) {
            return $html.'<p>'.$this->escape($report->translate(new Message('report.clean'))).'</p></section>';
        }

        $html .= '<p>'.$this->escape($report->translate(new Message('report.count', ['count' => \count($report)]))).'</p><ul>';

        foreach ($report->findings as $finding) {
            $html .= $this->item($report, $finding);
        }

        return $html.'</ul></section>';
    }

    private function item(Report $report, Finding $finding): string
    {
        $severity = $finding->severity->value;

        return '<li class="is'.ucfirst($severity).'" data-rule="'.$this->escape($finding->rule).'">'
            .'<strong>'.$this->escape($report->translate(new Message('severity.'.$severity))).'</strong> '
            .$this->escape($report->message($finding))
            .(null === $finding->subject ? '' : ' <code>'.$this->escape($finding->subject).'</code>')
            .'</li>';
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5, 'UTF-8');
    }
}
