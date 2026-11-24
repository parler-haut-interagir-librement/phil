<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Application;

use Phil\FaqBundle\Application\View\FaqPage;
use Phil\FaqBundle\Application\View\QuestionView;
use Phil\FaqBundle\Application\View\SectionView;
use Phil\FaqBundle\Domain\Contract\FaqRepository;
use Phil\FaqBundle\Domain\Model\QuestionTranslation;
use Phil\FaqBundle\Domain\Model\SectionTranslation;
use Phil\FaqBundle\Domain\ValueObject\Locale;

/**
 * The page of questions in a language: each question in that locale, else in its language alone, published only;
 * each section under its title in the same way, else under its key; the sections without a question left out. A
 * search keeps the questions whose wording or answer holds every word of it, accents and case aside.
 *
 * A page holds at most `maxQuestions` questions, the first ones in order, so that its cost stays bounded.
 */
final readonly class FaqPages
{
    /** The questions a page holds at most, by default. */
    public const int DEFAULT_MAX_QUESTIONS = 500;

    /**
     * @param positive-int $maxQuestions
     */
    public function __construct(
        private FaqRepository $repository,
        private QueryNormalizer $normalizer,
        private int $maxQuestions = self::DEFAULT_MAX_QUESTIONS,
    ) {
    }

    public function page(Locale $locale, string $query = ''): FaqPage
    {
        $query = trim(mb_substr(trim(mb_scrub(substr($query, 0, QueryNormalizer::MAX_INPUT_BYTES), 'UTF-8')), 0, QueryNormalizer::MAX_LENGTH, 'UTF-8'));
        $terms = $this->normalizer->normalize($query);
        $words = $this->normalizer->terms($terms);
        $chain = $locale->chain();
        $titles = $this->repository->sectionTranslations();
        $texts = $this->repository->questionTranslations(true);
        $bySection = [];
        $total = 0;

        foreach ($this->repository->questions() as $question) {
            $translation = $this->first($texts[$question->id] ?? [], $chain);

            if (!$translation instanceof QuestionTranslation) {
                continue;
            }

            if (++$total > $this->maxQuestions) {
                $total = $this->maxQuestions;

                break;
            }

            $view = $this->view($question->id, $question->anchor(), $translation, $question->helpfulYes(), $question->helpfulNo());

            if ($this->normalizer->matches($view->searchText, $words)) {
                $bySection[$question->section()->id][] = $view;
            }
        }

        $sections = [];

        foreach ($this->repository->sections() as $section) {
            if (isset($bySection[$section->id])) {
                $title = $this->first($titles[$section->id] ?? [], $chain);
                $sections[] = new SectionView($section->key(), $title instanceof SectionTranslation ? $title->title() : $section->key(), $bySection[$section->id]);
            }
        }

        return new FaqPage($locale->value, $sections, $query, $terms, $total);
    }

    private function view(string $id, string $anchor, QuestionTranslation $translation, int $yes, int $no): QuestionView
    {
        $answer = $translation->answer()->html;

        return new QuestionView(
            $id,
            $anchor,
            $translation->locale,
            $translation->text(),
            $answer,
            $this->normalizer->fold($translation->text().' '.QueryNormalizer::textOf($answer)),
            $translation->updatedAt(),
            $yes,
            $no,
        );
    }

    /**
     * @template T of object
     *
     * @param array<string, T>       $byLocale
     * @param non-empty-list<string> $chain
     *
     * @return T|null
     */
    private function first(array $byLocale, array $chain): ?object
    {
        foreach ($chain as $locale) {
            if (isset($byLocale[$locale])) {
                return $byLocale[$locale];
            }
        }

        return null;
    }
}
