<?php

declare(strict_types=1);

/*
 * The reader of stored drafts, on text nobody saved: whatever it reads, it returns a content whose JSON reads back to
 * the same JSON, or refuses it with InvalidDraft, and throws nothing else.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\FormsAutosave\Domain\Exception\InvalidDraft;
use Phil\FormsAutosave\Domain\ValueObject\DraftContent;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $content = DraftContent::fromJson($input);
    } catch (InvalidDraft) {
        return;
    }

    if (DraftContent::fromJson($content->toJson())->toJson() !== $content->toJson()) {
        throw new LogicException('A draft read back does not write the same JSON.');
    }
});
$config->setMaxLen(4096);
