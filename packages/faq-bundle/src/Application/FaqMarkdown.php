<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Application;

use Phil\FaqBundle\Application\View\FaqPage;

/**
 * The questions a page shows, as Markdown: one heading per section, one per question linked to its anchor, and the
 * text of each answer. It is the shape an `llms.txt` file, or a page of it, takes for a language model to read.
 */
final readonly class FaqMarkdown
{
    /**
     * @param string $title   the heading of the document
     * @param string $pageUrl the address of the page of questions, which the links to each question start with
     */
    public function export(FaqPage $page, string $title, string $pageUrl): string
    {
        $lines = ['# '.$this->line($title), ''];

        foreach ($page->sections as $section) {
            $lines[] = '## '.$this->line($section->title);
            $lines[] = '';

            foreach ($section->questions as $question) {
                $lines[] = '### ['.$this->line(str_replace(['[', ']'], ['\[', '\]'], $question->question)).']('.$pageUrl.'#'.$question->anchor.')';
                $lines[] = '';
                $lines[] = QueryNormalizer::textOf($question->answerHtml);
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * A text on one line, as a heading holds it.
     */
    private function line(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }
}
