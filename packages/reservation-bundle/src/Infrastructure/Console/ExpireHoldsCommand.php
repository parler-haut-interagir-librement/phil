<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Infrastructure\Console;

use Override;
use Phil\ReservationBundle\Application\Reservations;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `phil:reservation:expire-holds` marks the holds whose moment has come as expired, so that their events go out. A
 * hold past its moment frees its units whether it runs or not.
 */
#[AsCommand(name: 'phil:reservation:expire-holds', description: 'Marks the holds whose moment has come as expired')]
final class ExpireHoldsCommand extends Command
{
    public function __construct(
        private readonly Reservations $reservations,
        private readonly int $batch,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(\sprintf('%d holds expired.', $this->reservations->expireHolds($this->batch)));

        return Command::SUCCESS;
    }
}
