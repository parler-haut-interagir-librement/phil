<?php

declare(strict_types=1);

/*
 * The verification of an OpenAPI document on inputs nobody wrote: whatever it reads, it returns a report, or refuses
 * the document as unreadable, and never throws anything else nor warns.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\ApiContract\Exception\UnreadableDocument;
use Phil\ApiContract\OpenApi\ContractVerifier;
use Phil\ApiContract\OpenApi\OpenApiDocument;
use Phil\ApiContract\OpenApi\RuleLevel;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$verifier = ContractVerifier::withBuiltInRules();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($verifier): void {
    try {
        json_encode($verifier->verify(OpenApiDocument::fromJson($input), RuleLevel::Strict)->toArray(), \JSON_THROW_ON_ERROR);
    } catch (UnreadableDocument) {
        // A document refused whole is an answer.
    }
});
$config->setMaxLen(4096);
