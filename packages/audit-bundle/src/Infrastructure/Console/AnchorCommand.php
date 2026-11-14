<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Infrastructure\Console;

use Override;
use Phil\AuditBundle\Application\Anchoring;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:audit:anchor.
 *
 * Writes the anchor of every segment: every day, and at once after a restore.
 */
#[AsCommand(name: 'phil:audit:anchor', description: 'Writes the anchor of every segment of the audit trail')]
final class AnchorCommand extends Command
{
    public function __construct(
        private readonly Anchoring $anchoring,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $anchored = $this->anchoring->anchor($this->clock->now());

        foreach ($anchored as $segment => $head) {
            $output->writeln(\sprintf('%s: %s', $segment, $head));
        }

        $output->writeln(\sprintf('%d segment(s) anchored.', \count($anchored)));

        return Command::SUCCESS;
    }
}
