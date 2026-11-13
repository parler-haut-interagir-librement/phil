<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Infrastructure\Console;

use Phil\StatusPageBundle\Application\SnapshotPush;
use Phil\StatusPageBundle\Domain\Exception\UnknownSnapshotTarget;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * `phil:status-page:push [target]`: publishes a snapshot of the page to one target, or to every target, and fails when
 * one of them did not take it.
 */
#[AsCommand(name: self::NAME, description: 'Publishes a static snapshot of the status page outside the monitored infrastructure.')]
final class PushCommand extends Command
{
    public const string NAME = 'phil:status-page:push';

    public function __construct(
        private readonly SnapshotPush $push,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The target to publish to; every target when omitted.')]
        ?string $target = null,
    ): int {
        try {
            $report = $this->push->push($target);
        } catch (UnknownSnapshotTarget $unknownSnapshotTarget) {
            $io->error($unknownSnapshotTarget->getMessage());

            return self::INVALID;
        }

        foreach ($report->published as $name) {
            $io->writeln(\sprintf('Published to <info>%s</info>.', $name));
        }

        foreach ($report->failed as $name => $reason) {
            $io->writeln(\sprintf('<error>Not published to %s: %s.</error>', $name, $reason));
        }

        return $report->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
