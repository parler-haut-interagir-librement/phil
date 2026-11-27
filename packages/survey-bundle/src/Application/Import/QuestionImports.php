<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Application\Import;

use Phil\ImportWizard\Contract\RowSource;
use Phil\ImportWizard\Csv\CsvRowSource;
use Phil\ImportWizard\Exception\MappingIncomplete;
use Phil\ImportWizard\Exception\PlanChanged;
use Phil\ImportWizard\Exception\SourceRefused;
use Phil\ImportWizard\Import\Importer;
use Phil\ImportWizard\Import\ImportResult;
use Phil\ImportWizard\Plan\ImportPlan;
use Phil\ImportWizard\Plan\ImportPlanner;
use Phil\ImportWizard\Profile\ColumnMapping;
use Phil\SurveyBundle\Application\Questionnaires;
use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;

/**
 * The CSV import of the questions of a questionnaire: the file read within the bounds of `QuestionCsvProfile`, the dry
 * run that previews each row as a question created, replaced or refused, and the import of the plan the person
 * confirmed. It keeps nothing between calls: each makes its own target.
 */
final readonly class QuestionImports
{
    public function __construct(
        private Questionnaires $questionnaires,
        private ImportPlanner $planner = new ImportPlanner(),
        private Importer $importer = new Importer(),
    ) {
    }

    /**
     * Reads CSV text within the bounds of a file of questions.
     *
     * @throws SourceRefused when the text is beyond them, not UTF-8, without header, or has a quote left open
     */
    public function read(string $csv): CsvRowSource
    {
        return CsvRowSource::fromString($csv, limits: QuestionCsvProfile::limits());
    }

    /**
     * The dry run of the file for the questionnaire of this key, which may not exist yet.
     *
     * @throws MappingIncomplete    when no header gives the identifier, the type or the label
     * @throws InvalidQuestionnaire when the key is not the key of a questionnaire
     */
    public function plan(RowSource $source, string $key, ?ColumnMapping $mapping = null): ImportPlan
    {
        return $this->planner->plan($source, QuestionCsvProfile::profile(), new QuestionImportTarget($this->questionnaires, $key), $mapping);
    }

    /**
     * Writes the plan to the questionnaire of this key, created under the title given when it does not exist.
     *
     * @param string|null $previewed the fingerprint of the plan the person confirmed
     *
     * @throws PlanChanged          when the plan is not the one previewed
     * @throws InvalidQuestionnaire when the key is not the key of a questionnaire
     */
    public function commit(ImportPlan $plan, string $key, ?string $title = null, ?string $previewed = null): ImportResult
    {
        return $this->importer->commit($plan, new QuestionImportTarget($this->questionnaires, $key, $title), $previewed);
    }
}
