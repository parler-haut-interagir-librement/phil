<?php

declare(strict_types=1);

/*
 * The count by Meek's method on ballots nobody wrote: the first byte gives the options, the second the seats, and
 * every byte after it a choice, a byte of 255 ending a ballot. Whatever the ballots, the count fills its seats when a
 * ballot chooses anything, every round adds its votes and its exhausted value up to the ballots, and nothing is
 * thrown.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Poll\Ballot;
use Phil\Poll\PollRules;
use Phil\PollBundle\Domain\Counting\Billionths;
use Phil\PollBundle\Domain\Counting\CandidateTally;
use Phil\PollBundle\Domain\Counting\MeekStv;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$method = new MeekStv();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($method): void {
    if (strlen($input) < 2) {
        return;
    }

    $bytes = array_map(\ord(...), str_split($input));
    $candidates = 2 + $bytes[0] % 9;
    $seats = 1 + $bytes[1] % ($candidates - 1);
    $options = array_map(static fn (int $i): string => 'o'.$i, range(0, $candidates - 1));
    $ballots = [];
    $ranking = [];

    foreach (array_slice($bytes, 2) as $byte) {
        if (255 === $byte) {
            $ballots[] = Ballot::of($ranking);
            $ranking = [];

            continue;
        }

        $choice = $options[$byte % $candidates];

        if (!in_array($choice, $ranking, true)) {
            $ranking[] = $choice;
        }
    }

    $ballots[] = Ballot::of($ranking);
    $election = $method->elect(PollRules::ranked($options, abstention: true), $ballots, $seats);
    $voting = $election->ballots - $election->abstentions;

    if ($voting > 0 && count($election->elected) !== $seats) {
        throw new LogicException(sprintf('%d seats, %d elected.', $seats, count($election->elected)));
    }

    foreach ($election->rounds as $round) {
        $held = array_sum(array_map(static fn (CandidateTally $tally): int => $tally->votes->numerator, $round->tallies));

        if ($held + $round->exhausted->numerator !== $voting * Billionths::SCALE) {
            throw new LogicException(sprintf('Round %d holds %d billionths of %d ballots.', $round->number, $held + $round->exhausted->numerator, $voting));
        }
    }
});
$config->setMaxLen(2048);
