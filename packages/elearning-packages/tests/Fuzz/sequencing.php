<?php

declare(strict_types=1);

/*
 * The sequencing of SCORM on manifests nobody wrote: each is read into an activity tree, or refused as a package; the
 * tree is then walked by every navigation request in turn, each answer leaving a state that reads back from its plain
 * values; nothing else is thrown.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\PackageLimits;
use Phil\ElearningPackages\Sequencing\AttemptResult;
use Phil\ElearningPackages\Sequencing\NavigationRequest;
use Phil\ElearningPackages\Sequencing\Sequencer;
use Phil\ElearningPackages\Sequencing\SequencingReader;
use Phil\ElearningPackages\Sequencing\SequencingState;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$reader = new SequencingReader(new PackageLimits(manifestBytes: 65_536, depth: 8, items: 100));
$sequencer = new Sequencer();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($reader, $sequencer): void {
    try {
        $tree = $reader->read($input);
    } catch (PackageRefused) {
        return;
    }

    $state = SequencingState::begin(crc32($input));
    $activities = $tree->activities();

    foreach (NavigationRequest::cases() as $index => $request) {
        $target = $activities[$index % count($activities)]->identifier;
        $state = $sequencer->navigate($tree, $state, $request, $target)->state;

        if (null !== $state->current && $state->activity($state->current)->active && $tree->get($state->current)->isLeaf()) {
            $state = $sequencer->report($tree, $state, new AttemptResult(0 === $index % 2, 0 === $index % 3, ($index % 5) / 2 - 1));
        }

        if (SequencingState::fromArray($state->toArray(), $tree)->toArray() !== $state->toArray()) {
            throw new LogicException('A state does not read back from its plain values.');
        }
    }
});
$config->setMaxLen(8192);
