<?php

declare(strict_types=1);

/*
 * The reading of questionnaire documents nobody wrote, by the JSON exchange format. Each input gives a questionnaire
 * or InvalidQuestionnaire — never another exception —, and a questionnaire read is written back to a document that
 * reads to the same questionnaire, whose logic judges an empty answer and chooses an ending.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\SurveyBundle\Application\QuestionnaireJson;
use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $questionnaire = QuestionnaireJson::decode($input);
    } catch (InvalidQuestionnaire) {
        return;
    }

    $again = QuestionnaireJson::decode(QuestionnaireJson::encode($questionnaire));

    if ($again->toArray() !== $questionnaire->toArray()) {
        throw new LogicException('A questionnaire written and read back is another.');
    }

    $verdict = $questionnaire->logic->judge([]);
    $questionnaire->logic->ending([...$verdict->answer, ...$verdict->computed]);
});
$config->setMaxLen(8192);
