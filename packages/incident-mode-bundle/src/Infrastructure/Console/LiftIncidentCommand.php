<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Infrastructure\Console;

use Phil\IncidentMode\Actor;
use Phil\IncidentMode\Exception\IncidentModeException;
use Phil\IncidentMode\IncidentMode;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * `phil:incident:lift`: ends an incident in force before its end, in the name of an actor; or, with `--expired`,
 * records the end of every incident past it — the sweep a cron job runs where Messenger and Scheduler are not.
 */
#[AsCommand(name: 'phil:incident:lift', description: 'Lifts an incident in force, or records the end of every expired one.')]
final readonly class LiftIncidentCommand
{
    public function __construct(
        private IncidentMode $incidents,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The identifier of the incident, 32 hexadecimal characters.')]
        ?string $id = null,
        #[Option('The stable identifier of who lifts it, never an e-mail address.')]
        ?string $actor = null,
        #[Option('Records the end of every incident past it instead.')]
        bool $expired = false,
    ): int {
        if ($expired) {
            $io->success(\sprintf('%d expired incidents recorded as lifted.', $this->incidents->liftExpired()));

            return Command::SUCCESS;
        }

        if (null === $id || null === $actor) {
            $io->error('Give the incident to lift and who lifts it with --actor, or --expired alone.');

            return Command::INVALID;
        }

        try {
            $activation = $this->incidents->lift($id, Actor::of($actor));
        } catch (IncidentModeException $incidentModeException) {
            $io->error($incidentModeException->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('The incident %s is lifted.', $activation->id));

        return Command::SUCCESS;
    }
}
