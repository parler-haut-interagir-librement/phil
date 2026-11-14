<?php

declare(strict_types=1);

/*
 * The JsonLogic reader on rules nobody wrote: whatever it reads, it gives an expression or refuses with InvalidLogic,
 * never throws anything else nor warns; an expression it gives writes a canonical rule that reads back to the same
 * rule, and evaluates against an answer without throwing.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\FormLogic\Evaluation\Evaluator;
use Phil\FormLogic\Exception\InvalidLogic;
use Phil\FormLogic\JsonLogic\JsonLogicReader;
use Phil\FormLogic\JsonLogic\JsonLogicWriter;
use Phil\FormLogic\JsonLogic\Limits;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$reader = new JsonLogicReader(new Limits(maxBytes: 4096, maxDepth: 12, maxNodes: 200));
$writer = new JsonLogicWriter();
$evaluator = new Evaluator();
$answer = ['a' => '12', 'b' => ['x', 'y'], 'c' => true, 'd' => null, 'e' => ['f' => 2.5], 'g' => ''];

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($reader, $writer, $evaluator, $answer): void {
    try {
        $expression = $reader->read($input);
    } catch (InvalidLogic) {
        return;
    }

    $canonical = $writer->write($expression);

    if ($writer->write($reader->read($canonical)) !== $canonical) {
        throw new LogicException('A canonical rule does not read back to itself.');
    }

    $evaluator->evaluate($expression, $answer);
    $evaluator->evaluate($expression, []);
});
$config->setMaxLen(4096);
