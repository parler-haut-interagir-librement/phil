<?php

declare(strict_types=1);

/*
 * The reading of stored rows and submitted forms nobody wrote: a JSON document read back as a run, a selection, a
 * pending selection, a filter or a change set. Each input gives a value within its bounds or InvalidInput — never
 * another exception — and a value read writes back the same array.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\BulkActions\Change\ChangeSet;
use Phil\BulkActions\Exception\InvalidInput;
use Phil\BulkActions\Run\BulkRun;
use Phil\BulkActions\Selection\FilterCriteria;
use Phil\BulkActions\Selection\PendingSelection;
use Phil\BulkActions\Selection\Selection;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$readers = [
    static fn (array $data): array => BulkRun::fromArray($data)->toArray(),
    static fn (array $data): array => Selection::fromArray($data)->toArray(),
    static fn (array $data): array => PendingSelection::fromArray($data)->toArray(),
    static fn (array $data): array => FilterCriteria::fromArray($data)->toArray(),
    static fn (array $data): array => ChangeSet::fromSubmission(
        is_array($data['values'] ?? null) ? $data['values'] : [],
        is_array($data['apply'] ?? null) ? $data['apply'] : [],
    )->values,
];

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($readers): void {
    try {
        $data = json_decode($input, true, 16, \JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return;
    }

    if (!is_array($data)) {
        return;
    }

    foreach ($readers as $index => $read) {
        try {
            $written = $read($data);
        } catch (InvalidInput) {
            continue;
        }

        if ($index < 4 && $written !== $read($written)) {
            throw new LogicException('A stored row read back did not write the same array.');
        }
    }
});

$config->setMaxLen(8192);
