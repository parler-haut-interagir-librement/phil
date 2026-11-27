<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Application\Import;

use Phil\ImportWizard\Profile\Column;
use Phil\ImportWizard\Profile\ColumnType;
use Phil\ImportWizard\Profile\ImportProfile;
use Phil\ImportWizard\Source\SourceLimits;
use Phil\SurveyBundle\Domain\Status\QuestionType;
use Phil\SurveyBundle\Domain\ValueObject\Choice;
use Phil\SurveyBundle\Domain\ValueObject\Question;

/**
 * The CSV file of the questions of a questionnaire for phil/import-wizard: one row per question, by its identifier,
 * with the headers of its template and the usual English and French ones, and the bounds of the file read.
 */
final class QuestionCsvProfile
{
    public const string TYPE = 'survey-question';

    /** The bounds of a file: 1 MiB, 300 rows after the header — a questionnaire holds at most 200 questions — and 32 cells a row. */
    public const int MAX_BYTES = 1_048_576;

    public const int MAX_ROWS = 300;

    public const int MAX_CELLS = 32;

    /** The longest cell of choices: 100 choices of a value and a label, with their separators. */
    public const int MAX_CHOICES_CELL = 100 * (64 + Choice::MAX_LABEL + 4);

    private function __construct()
    {
    }

    public static function profile(): ImportProfile
    {
        return new ImportProfile(self::TYPE, [
            new Column('question_id', required: true, aliases: ['id', 'identifier', 'question id', 'name', 'identifiant', 'nom', 'code'], maxLength: 64),
            new Column('type', required: true, aliases: ['question type', 'kind', 'type de question'], choices: array_map(static fn (QuestionType $type): string => $type->value, QuestionType::cases())),
            new Column('label', required: true, aliases: ['question', 'question text', 'text', 'stem', 'libellé', 'énoncé', 'texte'], maxLength: Choice::MAX_LABEL),
            new Column('required', ColumnType::Boolean, aliases: ['mandatory', 'obligatoire', 'requis', 'requise']),
            new Column('help', aliases: ['hint', 'help text', 'description', 'aide', 'indice'], maxLength: Question::MAX_HELP),
            new Column('choices', aliases: ['options', 'answers', 'columns', 'choix', 'réponses', 'colonnes'], maxLength: self::MAX_CHOICES_CELL),
            new Column('rows', aliases: ['matrix rows', 'statements', 'lignes', 'lignes de la matrice'], maxLength: Question::MAX_ROWS * (64 + Choice::MAX_LABEL + 4)),
            new Column('min', ColumnType::Integer, aliases: ['minimum', 'lowest', 'minimum value', 'valeur minimale']),
            new Column('max', ColumnType::Integer, aliases: ['maximum', 'highest', 'maximum value', 'valeur maximale']),
            new Column('max_length', ColumnType::Integer, aliases: ['length', 'maximum length', 'longueur', 'longueur maximale']),
            new Column('section', aliases: ['section name', 'section id', 'page', 'partie'], maxLength: 64),
            new Column('section_title', aliases: ['section title', 'section heading', 'titre de section', 'titre de la section'], maxLength: Choice::MAX_LABEL),
        ], ['question_id'], [new QuestionRowRule()]);
    }

    /**
     * The bounds of a file of questions, tighter than the library's defaults.
     */
    public static function limits(): SourceLimits
    {
        return new SourceLimits(self::MAX_BYTES, self::MAX_ROWS, self::MAX_CELLS);
    }
}
