<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Application\Import;

use Override;
use Phil\ImportWizard\Contract\ImportTarget;
use Phil\ImportWizard\Import\Batch;
use Phil\ImportWizard\Plan\CellError;
use Phil\ImportWizard\Plan\NaturalKey;
use Phil\SurveyBundle\Application\Exception\InvalidQuestionRow;
use Phil\SurveyBundle\Application\Questionnaires;
use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;
use Phil\SurveyBundle\Domain\ValueObject\Question;
use Phil\SurveyBundle\Domain\ValueObject\Questionnaire;
use Phil\SurveyBundle\Domain\ValueObject\Section;

/**
 * One questionnaire as the target of phil/import-wizard, made for one import and dropped after it.
 *
 * A row whose identifier the questionnaire holds replaces that question in place, keeping its condition, which the file
 * does not carry; a row that names another section moves it to the end of that section. A new identifier is added at
 * the end of the section the row names, created when the questionnaire has none of that name, or of the last section.
 * A questionnaire that does not exist yet is created by the first batch, under the title given, with one ending.
 *
 * The rows of a batch are applied together; when the questionnaire refuses them, they are applied one at a time, and
 * only those it refuses are refused. The questionnaire is saved once per batch.
 */
final class QuestionImportTarget implements ImportTarget
{
    /** The section of the rows that name none, when the questionnaire has no section yet. */
    public const string DEFAULT_SECTION = 'questions';

    /** The ending of a questionnaire the import creates, which every respondent reaches. */
    public const array DEFAULT_ENDING = ['name' => 'thanks', 'title' => 'Thank you'];

    private ?Questionnaire $questionnaire;

    /**
     * @param string $title the title of the questionnaire, should the import create it; its key when none
     *
     * @throws InvalidQuestionnaire when the key is not the key of a questionnaire
     */
    public function __construct(
        private readonly Questionnaires $questionnaires,
        private readonly string $key,
        private readonly ?string $title = null,
    ) {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $key)) {
            throw InvalidQuestionnaire::because('The key of a questionnaire is 1 to 64 lower-case letters, digits, "_" or "-".');
        }

        $this->questionnaire = $questionnaires->find($key);
    }

    #[Override]
    public function type(): string
    {
        return QuestionCsvProfile::TYPE;
    }

    #[Override]
    public function existing(string $type, array $keys): array
    {
        if (QuestionCsvProfile::TYPE !== $type || !$this->questionnaire instanceof Questionnaire) {
            return [];
        }

        $questionnaire = $this->questionnaire;

        return array_values(array_filter($keys, static fn (NaturalKey $key): bool => $questionnaire->question($key->parts[0] ?? '') instanceof Question));
    }

    #[Override]
    public function write(Batch $batch): array
    {
        $before = $this->questionnaire;
        $refused = [];
        $read = [];

        foreach ($batch->rows as $row) {
            try {
                $read[$row->line] = QuestionRows::read($row->values, $before?->question($row->key->parts[0] ?? '')?->shownWhen);
            } catch (InvalidQuestionRow $invalidQuestionRow) {
                $refused[$row->line] = [CellError::refusedByTarget($invalidQuestionRow->column, $invalidQuestionRow->getMessage())];
            }
        }

        if ([] === $read) {
            return $refused;
        }

        try {
            $this->questionnaire = $this->build(array_reduce($read, $this->place(...), $before->sections ?? []));
        } catch (InvalidQuestionnaire) {
            $refused += $this->oneByOne($read);
        }

        $accepted = array_diff(array_keys($read), array_keys($refused));

        if (!$this->questionnaire instanceof Questionnaire || [] === $accepted) {
            return $refused;
        }

        try {
            $this->questionnaires->save($this->questionnaire);
        } catch (InvalidQuestionnaire $invalidQuestionnaire) {
            $this->questionnaire = $before;

            foreach ($accepted as $line) {
                $refused[$line] = [CellError::refusedByTarget(null, $invalidQuestionnaire->getMessage())];
            }
        }

        return $refused;
    }

    /**
     * Applies the rows one at a time, when the questionnaire refuses them together, to refuse only those it refuses.
     *
     * @param non-empty-array<int, ImportedQuestion> $read by line
     *
     * @return array<int, non-empty-list<CellError>> the errors of the rows refused, by line
     */
    private function oneByOne(array $read): array
    {
        $refused = [];

        foreach ($read as $line => $imported) {
            try {
                $this->questionnaire = $this->build($this->place($this->questionnaire->sections ?? [], $imported));
            } catch (InvalidQuestionnaire $invalidQuestionnaire) {
                $refused[$line] = [CellError::refusedByTarget(null, $invalidQuestionnaire->getMessage())];
            }
        }

        return $refused;
    }

    /**
     * The sections with the question of the row placed in them.
     *
     * @param list<Section> $sections
     *
     * @return list<Section>
     *
     * @throws InvalidQuestionnaire when a section would break a rule with it
     */
    private function place(array $sections, ImportedQuestion $imported): array
    {
        $question = $imported->question;
        $from = $this->holding($sections, $question->name);
        $last = array_key_last($sections);
        $name = $imported->section ?? (null !== $from ? $sections[$from]->name : (null === $last ? self::DEFAULT_SECTION : $sections[$last]->name));

        if (null !== $from) {
            $stays = $sections[$from]->name === $name;
            $kept = $stays
                ? array_map(static fn (Question $held): Question => $held->name === $question->name ? $question : $held, $sections[$from]->questions)
                : array_values(array_filter($sections[$from]->questions, static fn (Question $held): bool => $held->name !== $question->name));
            $sections = $this->replace($sections, $from, [] === $kept ? null : $this->with($sections[$from], $kept));

            if ($stays) {
                return $sections;
            }
        }

        $index = $this->find($sections, $name);

        return null === $index
            ? [...$sections, new Section($name, $imported->sectionTitle ?? (null === $imported->section ? $this->newTitle() : $name), [$question])]
            : $this->replace($sections, $index, $this->with($sections[$index], [...$sections[$index]->questions, $question]));
    }

    /**
     * The questionnaire with these sections, not saved.
     *
     * @param list<Section> $sections
     *
     * @throws InvalidQuestionnaire when the questionnaire would break a rule with them
     */
    private function build(array $sections): Questionnaire
    {
        $data = $this->questionnaire?->toArray() ?? ['key' => $this->key, 'title' => $this->newTitle(), 'endings' => [self::DEFAULT_ENDING]];
        $data['sections'] = array_map(static fn (Section $section): array => $section->toArray(), $sections);

        return Questionnaire::fromArray($data);
    }

    private function newTitle(): string
    {
        return $this->title ?? $this->key;
    }

    /**
     * @param list<Section> $sections
     *
     * @return int|null the index of the section that holds the question
     */
    private function holding(array $sections, string $question): ?int
    {
        foreach ($sections as $index => $section) {
            foreach ($section->questions as $held) {
                if ($held->name === $question) {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * @param list<Section> $sections
     *
     * @return list<Section> the sections with the one at the index replaced, or removed when null
     */
    private function replace(array $sections, int $index, ?Section $section): array
    {
        $replaced = array_map(static fn (Section $held, int $at): ?Section => $at === $index ? $section : $held, $sections, array_keys($sections));

        return array_values(array_filter($replaced, static fn (?Section $held): bool => $held instanceof Section));
    }

    /**
     * @param list<Section> $sections
     */
    private function find(array $sections, string $name): ?int
    {
        foreach ($sections as $index => $section) {
            if ($section->name === $name) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param list<Question> $questions
     */
    private function with(Section $section, array $questions): Section
    {
        return new Section($section->name, $section->title, $questions, $section->description, $section->shownWhen);
    }
}
