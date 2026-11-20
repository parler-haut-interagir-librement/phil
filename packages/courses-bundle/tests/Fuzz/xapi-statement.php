<?php

declare(strict_types=1);

/*
 * The checks of the statements of an xAPI activity on statements nobody wrote, decoded from JSON: each is accepted or
 * refused as a statement; nothing else is thrown, and nothing warns. An accepted statement names the actor and the
 * registration of the launch, and the outcome read from it is about the activity launched only.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\CoursesBundle\Application\Xapi\XapiContext;
use Phil\CoursesBundle\Application\Xapi\XapiOutcomes;
use Phil\CoursesBundle\Application\Xapi\XapiRules;
use Phil\CoursesBundle\Domain\Exception\StatementRefused;
use Phil\CoursesBundle\Domain\ValueObject\Outcome;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$context = new XapiContext('https://learn.example.org/', 'ada', 'https://example.org/xapi/knots', 'r-1', 's-1', 0.8);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($context): void {
    $statement = json_decode($input, true, 16);

    if (!is_array($statement)) {
        return;
    }

    try {
        $checked = XapiRules::check($statement, $context);
    } catch (StatementRefused) {
        return;
    }

    if (($checked['context']['registration'] ?? null) !== 'r-1' || ($checked['actor']['account']['name'] ?? null) !== 'ada') {
        throw new LogicException('A statement of another launch is accepted.');
    }

    if (XapiOutcomes::of($checked, $context) instanceof Outcome && ($checked['object']['id'] ?? null) !== 'https://example.org/xapi/knots') {
        throw new LogicException('A statement about another activity moves the progress.');
    }
});
$config->setMaxLen(2048);
