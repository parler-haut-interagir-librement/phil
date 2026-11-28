<?php

declare(strict_types=1);

/*
 * The rule reader on rules nobody wrote: whatever it reads, it gives a rule or refuses with InvalidRule or
 * LimitExceeded, never throws anything else nor warns; a rule it gives writes a canonical form that reads back to the
 * same form, and evaluates against facts without throwing.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Rules\Exception\InvalidRule;
use Phil\Rules\Exception\LimitExceeded;
use Phil\Rules\Fact\Facts;
use Phil\Rules\Json\RuleReader;
use Phil\Rules\Json\RuleWriter;
use Phil\Rules\Limits;
use Phil\Rules\Type\ConditionTypes;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$reader = new RuleReader(ConditionTypes::builtIn(), new Limits(maxBytes: 4096, maxDepth: 12, maxNodes: 200, maxEvaluations: 200));
$writer = new RuleWriter();
$facts = ['a' => 'CA', 'b' => ['x', 'y'], 'c' => true, 'd' => null, 'n' => 2.5, 'when' => new DateTimeImmutable('2026-11-21')];

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($reader, $writer, $facts): void {
    try {
        $rule = $reader->fromJson($input);
    } catch (InvalidRule|LimitExceeded) {
        return;
    }

    $canonical = $writer->toJson($rule);

    if ($writer->toJson($reader->fromJson($canonical)) !== $canonical) {
        throw new LogicException('A canonical rule does not read back to itself.');
    }

    $rule->evaluate(Facts::of($facts));
    $rule->evaluate(Facts::of([]));
});
$config->setMaxLen(4096);
