<?php

declare(strict_types=1);

/*
 * The parser on messages nobody wrote: whatever it reads, it returns a report and never throws.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\BounceParser\BounceKind;
use Phil\BounceParser\BounceParser;
use Phil\BounceParser\Rule\Field;
use Phil\BounceParser\Rule\Rule;
use Phil\BounceParser\Rule\Rules;
use Phil\BounceParser\Rule\RuleScope;
use Phil\BounceParser\Verp;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$parser = new BounceParser(
    new Rules(
        Rule::containing('full', Field::Body, 'mailbox full', BounceKind::Soft),
        Rule::matching('header', Field::Header, '/^yes$/i', BounceKind::AutoReply, scope: RuleScope::Always, header: 'X-Auto'),
    ),
    new Verp('bounces', 'example.net'),
);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($parser): void {
    $parser->parse($input);
});
$config->setMaxLen(8192);
