<?php

declare(strict_types=1);

/*
 * The reader of the CSV files people upload, on text nobody wrote: `CsvRowSource::fromString()` within small limits,
 * then the dry run of the three formats of a learning platform on what it read. Each returns or throws an
 * ImportWizardException; nothing else escapes, every row keeps a line within the text, and no row holds more cells than
 * the limit.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\ImportWizard\Contract\KeyLookup;
use Phil\ImportWizard\Csv\CsvRowSource;
use Phil\ImportWizard\Exception\ImportWizardException;
use Phil\ImportWizard\Lms\LmsProfiles;
use Phil\ImportWizard\Plan\ImportPlanner;
use Phil\ImportWizard\Source\SourceLimits;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$limits = new SourceLimits(8192, 256, 32);
$planner = new ImportPlanner();
$lookup = new class implements KeyLookup {
    public function existing(string $type, array $keys): array
    {
        return array_slice($keys, 0, intdiv(count($keys), 2));
    }
};

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($limits, $planner, $lookup): void {
    try {
        $source = CsvRowSource::fromString($input, limits: $limits);
    } catch (ImportWizardException) {
        return;
    }

    $lines = substr_count($input, "\n") + substr_count($input, "\r") + 1;

    foreach ($source->rows() as $row) {
        if ($row->line < 1 || $row->line > $lines || count($row->cells) > $limits->maxColumns) {
            throw new LogicException('A row is out of the text or of its limits.');
        }
    }

    foreach (LmsProfiles::all() as $profile) {
        try {
            $plan = $planner->plan($source, $profile, $lookup);
        } catch (ImportWizardException) {
            continue;
        }

        if (count($plan->preview()) !== count($plan->rows) + count($plan->rejections)) {
            throw new LogicException('A row is both planned and refused, or neither.');
        }
    }
});
$config->setMaxLen(8192);
