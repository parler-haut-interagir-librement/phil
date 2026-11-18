<?php

declare(strict_types=1);

/*
 * The rules of cmi5 on statements nobody wrote, decoded from JSON: each is accepted, with a verb cmi5 defines or none,
 * or refused as a statement; nothing else is thrown, and nothing warns. An accepted statement names the actor,
 * registration and session of the launch.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\CoursesBundle\Application\Cmi5\Cmi5Context;
use Phil\CoursesBundle\Application\Cmi5\StatementRules;
use Phil\CoursesBundle\Domain\Cmi5\Cmi5Iri;
use Phil\CoursesBundle\Domain\Cmi5\SessionState;
use Phil\CoursesBundle\Domain\Exception\StatementRefused;
use Phil\CoursesBundle\Domain\Progress\CompletionRequirement;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$context = new Cmi5Context('https://learn.example.org/', 'ada', 'https://learn.example.org/courses/activities/a1', 'https://example.org/au', 'https://learn.example.org/courses/c1', 'r-1', 's-1', CompletionRequirement::CompletedAndPassed, 0.8);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($context): void {
    $statement = json_decode($input, true, 16);

    if (!is_array($statement)) {
        return;
    }

    foreach (SessionState::cases() as $state) {
        try {
            StatementRules::check($statement, $context, $state, null);
        } catch (StatementRefused) {
            continue;
        }

        if (($statement['context']['registration'] ?? null) !== 'r-1' || ($statement['context']['extensions'][Cmi5Iri::SESSION] ?? null) !== 's-1' || ($statement['actor']['account']['name'] ?? null) !== 'ada') {
            throw new LogicException('A statement of another launch is accepted.');
        }
    }
});
$config->setMaxLen(2048);
