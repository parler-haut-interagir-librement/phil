<?php

declare(strict_types=1);

/*
 * The directives of a note nobody wrote, as a reader keeps an HTML comment: the note is a note, or directives whose
 * classes are valid class names and whose names noted are Marp's; nothing else is thrown, and nothing warns. A deck cut
 * from a block carrying that note keeps the same promise.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\Slides\Domain\CommentDirectives;
use Phil\Slides\Domain\Slicer;
use Phil\Ujo\Block;
use Phil\Ujo\Document;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$slicer = new Slicer();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($slicer): void {
    $directives = CommentDirectives::read($input);

    foreach ([...$directives?->spotClasses ?? [], ...$directives?->carriedClasses ?? []] as $class) {
        if (1 !== preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $class)) {
            throw new LogicException('A class that is no class name is kept.');
        }
    }

    $deck = $slicer->slice(new Document([
        new Block('a', 'paragraph', ['text' => 'One'], ['note' => ['text' => $input]]),
        new Block('b', 'delimiter', [], ['note' => ['text' => $input]]),
        new Block('c', 'paragraph', ['text' => 'Two']),
    ]));

    if (2 !== $deck->count()) {
        throw new LogicException('A note changes the number of slides.');
    }
});
$config->setMaxLen(CommentDirectives::MAX_BYTES + 64);
