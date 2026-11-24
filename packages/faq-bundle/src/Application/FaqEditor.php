<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Application;

use Phil\FaqBundle\Application\Exception\UnknownQuestion;
use Phil\FaqBundle\Application\Exception\UnknownSection;
use Phil\FaqBundle\Domain\Contract\FaqRepository;
use Phil\FaqBundle\Domain\Exception\InvalidFaq;
use Phil\FaqBundle\Domain\Model\Question;
use Phil\FaqBundle\Domain\Model\QuestionTranslation;
use Phil\FaqBundle\Domain\Model\Section;
use Phil\FaqBundle\Domain\Model\SectionTranslation;
use Phil\FaqBundle\Domain\ValueObject\Locale;
use Phil\Markup\Exception\MarkupException;
use Phil\Markup\Level;
use Phil\Markup\MarkedUpText;
use Phil\Markup\Markup;
use Phil\Markup\MarkupFormat;
use Phil\Slug\InvalidSlug;
use Phil\Slug\Slug;
use Phil\Slug\Slugger;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Writes the questions: sections and their titles, questions and their translations, their order, their publication.
 *
 * An answer is written in Markdown, HTML or another format of `phil/markup`, and kept with its HTML purified at the
 * `forum` level: paragraphs, emphasis, links, lists, quotes and images, nothing that runs. An anchor or a key is made
 * once, from the first wording or title, made unique by a number, and kept when the wording changes. Order is given
 * by naming the question or the section to come before, never by a number.
 */
final readonly class FaqEditor
{
    /** The longest slug made from a wording, leaving room for a number that makes it unique. */
    private const int SLUG_LENGTH = 90;

    /** How many numbered anchors are tried before giving up. */
    private const int MAX_TRIES = 100;

    public function __construct(
        private FaqRepository $repository,
        private Markup $markup,
        private Slugger $slugger,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Adds a section at the end, its key made from its title unless one is given.
     *
     * @throws InvalidFaq when the title or the key cannot be used
     */
    public function addSection(Locale $locale, string $title, ?string $key = null): Section
    {
        $slug = null === $key ? $this->unique($title, fn (string $candidate): bool => $this->repository->sectionByKey($candidate) instanceof Section) : $this->slug($key);

        if (null !== $key && $this->repository->sectionByKey($slug->toString()) instanceof Section) {
            throw InvalidFaq::because(\sprintf('A section already has the key "%s".', $slug->toString()));
        }

        $section = Section::create(Uuid::v7()->toRfc4122(), $slug, \count($this->repository->sections()));
        $this->repository->save($section, SectionTranslation::of(Uuid::v7()->toRfc4122(), $section, $locale, $title));

        return $section;
    }

    /**
     * Gives a section its title in a language, a new one or a changed one; its key stays.
     *
     * @throws UnknownSection when no section has this identifier
     * @throws InvalidFaq     when the title cannot be used
     */
    public function retitleSection(string $id, Locale $locale, string $title): void
    {
        $section = $this->section($id);
        $translation = $this->repository->sectionTranslation($section, $locale->value);

        if ($translation instanceof SectionTranslation) {
            $translation->retitle($title);
        } else {
            $translation = SectionTranslation::of(Uuid::v7()->toRfc4122(), $section, $locale, $title);
        }

        $this->repository->save($translation);
    }

    /**
     * Puts a section before another, or at the end when no other is named.
     *
     * @throws UnknownSection when either section does not exist
     */
    public function placeSection(string $id, ?string $before = null): void
    {
        $section = $this->section($id);
        $next = null === $before ? null : $this->section($before);
        $order = $this->placed($this->repository->sections(), $section, $next);

        foreach ($order as $position => $one) {
            $one->moveTo($position);
        }

        $this->repository->save(...$order);
    }

    /**
     * Removes a section, its questions and every translation.
     *
     * @throws UnknownSection when no section has this identifier
     */
    public function removeSection(string $id): void
    {
        $this->repository->remove($this->section($id));
        $this->renumberSections();
    }

    /**
     * Adds a question at the end of a section, its anchor made from its wording unless one is given.
     *
     * @throws UnknownSection when no section has this identifier
     * @throws InvalidFaq     when the wording, the answer or the anchor cannot be used
     */
    public function addQuestion(string $sectionId, Locale $locale, string $text, string $answer, MarkupFormat|string $format = MarkupFormat::Markdown, bool $published = true, ?string $anchor = null): Question
    {
        $section = $this->section($sectionId);
        $slug = null === $anchor ? $this->unique($text, fn (string $candidate): bool => $this->repository->questionByAnchor($candidate) instanceof Question) : $this->slug($anchor);

        if (null !== $anchor && $this->repository->questionByAnchor($slug->toString()) instanceof Question) {
            throw InvalidFaq::because(\sprintf('A question already has the anchor "%s".', $slug->toString()));
        }

        $question = Question::create(Uuid::v7()->toRfc4122(), $section, $slug, \count($this->repository->questionsOf($section)));
        $translation = QuestionTranslation::of(Uuid::v7()->toRfc4122(), $question, $locale, $text, $this->answer($answer, $format), $published, $this->clock->now());
        $this->repository->save($question, $translation);

        return $question;
    }

    /**
     * Gives a question its wording and answer in a language, new or changed; its anchor stays. A new translation is
     * published when `published` says so, unpublished when it is null; a changed one keeps its state unless told.
     *
     * @throws UnknownQuestion when no question has this identifier
     * @throws InvalidFaq      when the wording or the answer cannot be used
     */
    public function rewriteQuestion(string $id, Locale $locale, string $text, string $answer, MarkupFormat|string $format = MarkupFormat::Markdown, ?bool $published = null): void
    {
        $question = $this->question($id);
        $translation = $this->repository->questionTranslation($question, $locale->value);
        $now = $this->clock->now();
        $answered = $this->answer($answer, $format);

        if ($translation instanceof QuestionTranslation) {
            $translation->rewrite($text, $answered, $now);

            if (null !== $published) {
                $translation->publish($published, $now);
            }
        } else {
            $translation = QuestionTranslation::of(Uuid::v7()->toRfc4122(), $question, $locale, $text, $answered, true === $published, $now);
        }

        $this->repository->save($translation);
    }

    /**
     * Publishes or withdraws a question in one language.
     *
     * @throws UnknownQuestion when the question, or its translation in this language, does not exist
     */
    public function publish(string $id, Locale $locale, bool $published): void
    {
        $question = $this->question($id);
        $translation = $this->repository->questionTranslation($question, $locale->value) ?? throw UnknownQuestion::named($id.' ('.$locale->value.')');
        $translation->publish($published, $this->clock->now());

        $this->repository->save($translation);
    }

    /**
     * Puts a question into a section, before another question of it, or at its end when none is named.
     *
     * @throws UnknownQuestion when either question does not exist
     * @throws UnknownSection  when the section does not exist
     * @throws InvalidFaq      when the question named to come after is in another section
     */
    public function placeQuestion(string $id, string $sectionId, ?string $before = null): void
    {
        $question = $this->question($id);
        $section = $this->section($sectionId);
        $next = null === $before ? null : $this->question($before);

        if ($next instanceof Question && $next->section()->id !== $section->id) {
            throw InvalidFaq::because('A question is placed before another question of the same section.');
        }

        $previous = $question->section();
        $order = $this->placed($this->repository->questionsOf($section), $question, $next);

        foreach ($order as $position => $one) {
            $one->moveTo($section, $position);
        }

        $this->repository->save(...$order);

        if ($previous->id !== $section->id) {
            $this->renumberQuestions($previous);
        }
    }

    /**
     * Removes a question and its translations.
     *
     * @throws UnknownQuestion when no question has this identifier
     */
    public function removeQuestion(string $id): void
    {
        $question = $this->question($id);
        $this->repository->remove($question);
        $this->renumberQuestions($question->section());
    }

    /**
     * @throws UnknownSection when no section has this identifier
     */
    public function section(string $id): Section
    {
        return $this->repository->section($id) ?? throw UnknownSection::named($id);
    }

    /**
     * @throws UnknownQuestion when no question has this identifier
     */
    public function question(string $id): Question
    {
        return $this->repository->question($id) ?? throw UnknownQuestion::named($id);
    }

    /**
     * The list with the object taken out and put back before the next one, or at the end.
     *
     * @template T of Section|Question
     *
     * @param list<T> $list
     * @param T       $object
     * @param T|null  $next
     *
     * @return list<T>
     */
    private function placed(array $list, Section|Question $object, Section|Question|null $next): array
    {
        $order = array_values(array_filter($list, static fn (Section|Question $one): bool => $one->id !== $object->id));
        $at = null === $next ? false : array_search($next, $order, true);
        array_splice($order, false === $at ? \count($order) : $at, 0, [$object]);

        return $order;
    }

    private function renumberSections(): void
    {
        $sections = $this->repository->sections();

        foreach ($sections as $position => $section) {
            $section->moveTo($position);
        }

        $this->repository->save(...$sections);
    }

    private function renumberQuestions(Section $section): void
    {
        $questions = $this->repository->questionsOf($section);

        foreach ($questions as $position => $question) {
            $question->moveTo($section, $position);
        }

        $this->repository->save(...$questions);
    }

    /**
     * @throws InvalidFaq when the answer cannot be read in its format
     */
    private function answer(string $source, MarkupFormat|string $format): MarkedUpText
    {
        try {
            return $this->markup->text($source, $format, Level::Forum);
        } catch (MarkupException $markupException) {
            throw InvalidFaq::because('The answer cannot be read in its format: '.$markupException->getMessage());
        }
    }

    /**
     * A slug of the text, numbered until nothing uses it.
     *
     * @param callable(string): bool $taken
     *
     * @throws InvalidFaq when the text holds no letter or digit, or every number is taken
     */
    private function unique(string $text, callable $taken): Slug
    {
        try {
            $base = $this->slugger->slug($text)->toString();
        } catch (InvalidSlug) {
            throw InvalidFaq::because('A wording or a title holds a letter or a digit.');
        }

        $base = rtrim(substr($base, 0, self::SLUG_LENGTH), '-');

        for ($try = 1; $try <= self::MAX_TRIES; ++$try) {
            $candidate = 1 === $try ? $base : $base.'-'.$try;

            if (!$taken($candidate)) {
                return Slug::fromString($candidate);
            }
        }

        throw InvalidFaq::because(\sprintf('Every anchor from "%s" to "%s-%d" is taken.', $base, $base, self::MAX_TRIES));
    }

    /**
     * @throws InvalidFaq when it is not a slug
     */
    private function slug(string $value): Slug
    {
        try {
            return Slug::fromString($value);
        } catch (InvalidSlug) {
            throw InvalidFaq::because('A key or an anchor is lowercase letters and digits, in words joined by hyphens.');
        }
    }
}
