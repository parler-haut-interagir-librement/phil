<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Application\View;

use DateTimeImmutable;

/**
 * A published question as a page shows it, in the language found for it.
 */
final readonly class QuestionView
{
    /**
     * @param string $answerHtml the purified HTML of the answer
     * @param string $searchText the wording and the text of the answer, normalized as searches are
     */
    public function __construct(
        public string $id,
        public string $anchor,
        public string $locale,
        public string $question,
        public string $answerHtml,
        public string $searchText,
        public DateTimeImmutable $updatedAt,
        public int $helpfulYes,
        public int $helpfulNo,
    ) {
    }
}
