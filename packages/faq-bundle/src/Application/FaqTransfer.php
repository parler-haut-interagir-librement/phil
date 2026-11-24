<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Application;

use Phil\FaqBundle\Domain\Contract\FaqRepository;
use Phil\FaqBundle\Domain\Exception\InvalidFaq;
use Phil\FaqBundle\Domain\Model\Question;
use Phil\FaqBundle\Domain\Model\Section;
use Phil\FaqBundle\Domain\Model\SectionTranslation;
use Phil\FaqBundle\Domain\ValueObject\Locale;

/**
 * The questions as plain data, for a file: every section by its key with its titles, every question by its anchor with
 * its translations — wording, answer as written, format, publication —, in order.
 *
 * An import matches the sections by key and the questions by anchor: what exists is changed, what does not is added,
 * and the order becomes the file's. Nothing missing from the file is removed. The shape of the whole file is checked
 * before the first change, and the changes are made in one transaction: a value the model refuses undoes them all.
 *
 *     sections:
 *       - key: orders
 *         titles: { en: Orders, fr: Commandes }
 *         questions:
 *           - anchor: how-do-i-pay
 *             translations:
 *               en: { question: How do I pay?, answer: 'By card or **transfer**.', format: markdown, published: true }
 */
final readonly class FaqTransfer
{
    /** The most sections and questions a file holds. */
    public const int MAX_ITEMS = 5_000;

    public function __construct(
        private FaqRepository $repository,
        private FaqEditor $editor,
    ) {
    }

    /**
     * @return array{sections: list<array{key: string, titles: array<string, string>, questions: list<array{anchor: string, translations: array<string, array{question: string, answer: string, format: string, published: bool}>}>}>}
     */
    public function export(): array
    {
        $titles = $this->repository->sectionTranslations();
        $texts = $this->repository->questionTranslations(false);
        $sections = [];

        foreach ($this->repository->sections() as $section) {
            $questions = [];

            foreach ($this->repository->questionsOf($section) as $question) {
                $translations = [];

                foreach ($texts[$question->id] ?? [] as $locale => $translation) {
                    $translations[$locale] = ['question' => $translation->text(), 'answer' => $translation->answer()->source, 'format' => $translation->answer()->format, 'published' => $translation->isPublished()];
                }

                ksort($translations);
                $questions[] = ['anchor' => $question->anchor(), 'translations' => $translations];
            }

            $sectionTitles = array_map(static fn (SectionTranslation $title): string => $title->title(), $titles[$section->id] ?? []);
            ksort($sectionTitles);
            $sections[] = ['key' => $section->key(), 'titles' => $sectionTitles, 'questions' => $questions];
        }

        return ['sections' => $sections];
    }

    /**
     * @return array{sections: int, questions: int} how many sections and questions the file holds
     *
     * @throws InvalidFaq when the data is not the shape of an export, or a value breaks a rule of the model
     */
    public function import(mixed $data): array
    {
        $sections = $this->read($data);

        return $this->repository->transactional(function () use ($sections): array {
            $questions = 0;

            foreach ($sections as $sectionData) {
                $section = $this->section($sectionData['key'], $sectionData['titles']);
                $this->editor->placeSection($section->id);

                foreach ($sectionData['questions'] as $questionData) {
                    $question = $this->question($section, $questionData['anchor'], $questionData['translations']);
                    $this->editor->placeQuestion($question->id, $section->id);
                    ++$questions;
                }
            }

            return ['sections' => \count($sections), 'questions' => $questions];
        });
    }

    /**
     * @param non-empty-array<string, string> $titles
     */
    private function section(string $key, array $titles): Section
    {
        $section = $this->repository->sectionByKey($key);

        foreach ($titles as $locale => $title) {
            if ($section instanceof Section) {
                $this->editor->retitleSection($section->id, Locale::fromString($locale), $title);
            } else {
                $section = $this->editor->addSection(Locale::fromString($locale), $title, $key);
            }
        }

        return $section;
    }

    /**
     * @param non-empty-array<string, array{question: string, answer: string, format: string, published: bool}> $translations
     */
    private function question(Section $section, string $anchor, array $translations): Question
    {
        $question = $this->repository->questionByAnchor($anchor);

        foreach ($translations as $locale => $one) {
            if ($question instanceof Question) {
                $this->editor->rewriteQuestion($question->id, Locale::fromString($locale), $one['question'], $one['answer'], $one['format'], $one['published']);
            } else {
                $question = $this->editor->addQuestion($section->id, Locale::fromString($locale), $one['question'], $one['answer'], $one['format'], $one['published'], $anchor);
            }
        }

        return $question;
    }

    /**
     * @return list<array{key: string, titles: non-empty-array<string, string>, questions: list<array{anchor: string, translations: non-empty-array<string, array{question: string, answer: string, format: string, published: bool}>}>}>
     *
     * @throws InvalidFaq when the data is not the shape of an export
     */
    private function read(mixed $data): array
    {
        $sections = $this->list(\is_array($data) ? $data['sections'] ?? null : null, 'sections');
        $read = [];
        $items = 0;

        foreach ($sections as $s => $section) {
            $path = 'sections['.$s.']';
            $section = $this->map($section, $path);
            $questions = [];

            foreach ($this->list($section['questions'] ?? [], $path.'.questions') as $q => $question) {
                $at = $path.'.questions['.$q.']';
                $question = $this->map($question, $at);
                $translations = [];

                foreach ($this->map($question['translations'] ?? null, $at.'.translations') as $locale => $translation) {
                    $where = $at.'.translations.'.$locale;
                    $translation = $this->map($translation, $where);
                    $translations[$this->locale($locale, $where)] = [
                        'question' => $this->text($translation['question'] ?? null, $where.'.question'),
                        'answer' => $this->text($translation['answer'] ?? null, $where.'.answer'),
                        'format' => $this->text($translation['format'] ?? 'markdown', $where.'.format'),
                        'published' => $this->flag($translation['published'] ?? true, $where.'.published'),
                    ];
                }

                $questions[] = ['anchor' => $this->text($question['anchor'] ?? null, $at.'.anchor'), 'translations' => $this->filled($translations, $at.'.translations')];
            }

            $titles = [];

            foreach ($this->map($section['titles'] ?? null, $path.'.titles') as $locale => $title) {
                $titles[$this->locale($locale, $path.'.titles.'.$locale)] = $this->text($title, $path.'.titles.'.$locale);
            }

            $items += 1 + \count($questions);

            if ($items > self::MAX_ITEMS) {
                throw InvalidFaq::because(\sprintf('A file holds %d sections and questions at most.', self::MAX_ITEMS));
            }

            $read[] = ['key' => $this->text($section['key'] ?? null, $path.'.key'), 'titles' => $this->filled($titles, $path.'.titles'), 'questions' => $questions];
        }

        return $read;
    }

    /**
     * @return list<mixed>
     */
    private function list(mixed $value, string $path): array
    {
        return \is_array($value) && array_is_list($value) ? $value : throw InvalidFaq::because(\sprintf('%s is a list.', $path));
    }

    /**
     * @return array<array-key, mixed>
     */
    private function map(mixed $value, string $path): array
    {
        return \is_array($value) ? $value : throw InvalidFaq::because(\sprintf('%s is a map.', $path));
    }

    /**
     * @template T
     *
     * @param array<string, T> $values
     *
     * @return non-empty-array<string, T>
     */
    private function filled(array $values, string $path): array
    {
        return [] === $values ? throw InvalidFaq::because(\sprintf('%s holds one language at least.', $path)) : $values;
    }

    private function text(mixed $value, string $path): string
    {
        return \is_string($value) ? $value : throw InvalidFaq::because(\sprintf('%s is a text.', $path));
    }

    private function flag(mixed $value, string $path): bool
    {
        return \is_bool($value) ? $value : throw InvalidFaq::because(\sprintf('%s is true or false.', $path));
    }

    private function locale(int|string $locale, string $path): string
    {
        try {
            return Locale::fromString((string) $locale)->value;
        } catch (InvalidFaq) {
            throw InvalidFaq::because(\sprintf('%s is not under a locale.', $path));
        }
    }
}
