<?php

declare(strict_types=1);

/*
 * One respondent of the concurrency tests, in a process of its own: it loads the autoloader it is given, waits for
 * the instant every respondent starts at, then answers on a connection of its own. Its exit code says what happened:
 * 0 kept, 3 screened out by a full quota, 4 refused as answered already, 1 anything else.
 *
 *     php respondent.php <autoload.php> <database URL> <questionnaire JSON> <member> <colour> <start, microseconds>
 */

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Phil\SurveyBundle\Application\Exception\SurveyRefused;
use Phil\SurveyBundle\Application\QuestionnaireJson;
use Phil\SurveyBundle\Tests\Fixtures\Stage;

$arguments = is_array($_SERVER['argv'] ?? null) ? array_map(static fn (mixed $argument): string => is_string($argument) ? $argument : '', $_SERVER['argv']) : [];
[, $autoload, $url, $json, $member, $colour, $start] = $arguments + array_fill(0, 7, '');

require $autoload;

try {
    $stage = new Stage(DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql'])->parse($url)), create: false);
    $questionnaire = QuestionnaireJson::decode($json);

    while (hrtime(true) / 1000 < (int) $start) {
        usleep(100);
    }

    exit($stage->submissions->submit($questionnaire, ['colour' => $colour], $member)->kept ? 0 : 3);
} catch (SurveyRefused $surveyRefused) {
    exit(SurveyRefused::ALREADY_ANSWERED === $surveyRefused->reason ? 4 : 1);
} catch (Throwable $throwable) {
    fwrite(\STDERR, $throwable::class.': '.$throwable->getMessage()."\n");

    exit(1);
}
