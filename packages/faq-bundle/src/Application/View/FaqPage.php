<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Application\View;

/**
 * What a page of questions shows in one language: the sections that hold a published question, filtered by a search
 * when there is one.
 */
final readonly class FaqPage
{
    /**
     * @param list<SectionView> $sections the sections shown, in order, each with the questions the search found
     * @param string            $query    the search as typed, trimmed and cut to 100 characters
     * @param string            $terms    the search normalized
     * @param int               $total    how many published questions there are before the search
     */
    public function __construct(
        public string $locale,
        public array $sections,
        public string $query,
        public string $terms,
        public int $total,
    ) {
    }

    public function isSearch(): bool
    {
        return '' !== $this->terms;
    }

    /**
     * How many questions are shown.
     */
    public function found(): int
    {
        return array_sum(array_map(static fn (SectionView $section): int => \count($section->questions), $this->sections));
    }

    /**
     * Whether a search found nothing among questions that exist.
     */
    public function hasNoResult(): bool
    {
        return $this->isSearch() && [] === $this->sections;
    }

    /**
     * Every question shown, in order.
     *
     * @return list<QuestionView>
     */
    public function questions(): array
    {
        return array_merge(...array_map(static fn (SectionView $section): array => $section->questions, $this->sections));
    }
}
