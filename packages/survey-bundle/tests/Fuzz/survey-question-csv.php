<?php

declare(strict_types=1);

/*
 * The CSV files of questions people upload, on text nobody wrote: read within small bounds, planned by the profile of
 * the questions, each planned row read again as a question. Reading and planning return or throw an
 * ImportWizardException; a row the dry run accepts always reads as the question of its identifier, and that question
 * written and read back is the same.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\ImportWizard\Contract\KeyLookup;
use Phil\ImportWizard\Csv\CsvRowSource;
use Phil\ImportWizard\Exception\ImportWizardException;
use Phil\ImportWizard\Plan\ImportPlanner;
use Phil\ImportWizard\Source\SourceLimits;
use Phil\SurveyBundle\Application\Import\QuestionCsvProfile;
use Phil\SurveyBundle\Application\Import\QuestionRows;
use Phil\SurveyBundle\Domain\ValueObject\Question;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$limits = new SourceLimits(8192, 64, QuestionCsvProfile::MAX_CELLS);
$profile = QuestionCsvProfile::profile();
$planner = new ImportPlanner();
$lookup = new class implements KeyLookup {
    public function existing(string $type, array $keys): array
    {
        return array_slice($keys, 0, intdiv(count($keys), 2));
    }
};

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($limits, $profile, $planner, $lookup): void {
    try {
        $plan = $planner->plan(CsvRowSource::fromString($input, limits: $limits), $profile, $lookup);
    } catch (ImportWizardException) {
        return;
    }

    if (count($plan->preview()) !== count($plan->rows) + count($plan->rejections)) {
        throw new LogicException('A row is both planned and refused, or neither.');
    }

    foreach ($plan->rows as $row) {
        $question = QuestionRows::read($row->values)->question;

        if ($question->name !== $row->key->parts[0] || Question::fromArray($question->toArray())->toArray() !== $question->toArray()) {
            throw new LogicException('A row the dry run accepts is not read back as its question.');
        }
    }
});
$config->setMaxLen(8192);
