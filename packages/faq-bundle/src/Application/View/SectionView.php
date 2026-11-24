<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Application\View;

/**
 * A section as a page shows it: its key, which is its anchor, its title, and its questions in order.
 */
final readonly class SectionView
{
    /**
     * @param non-empty-list<QuestionView> $questions
     */
    public function __construct(
        public string $key,
        public string $title,
        public array $questions,
    ) {
    }
}
