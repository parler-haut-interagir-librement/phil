<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Infrastructure\Console;

use Phil\IncidentMode\Actor;
use Phil\IncidentMode\Exception\IncidentModeException;
use Phil\IncidentMode\IncidentMode;
use Phil\IncidentModeBundle\Application\DurationInput;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * `phil:incident:extend`: gives an incident in force a later end, within the longest duration of its preset.
 */
#[AsCommand(name: 'phil:incident:extend', description: 'Extends an incident in force, within what its preset allows.')]
final readonly class ExtendIncidentCommand
{
    public function __construct(
        private IncidentMode $incidents,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The identifier of the incident, 32 hexadecimal characters.')]
        string $id,
        #[Option('How much longer it lasts, such as 30m or 2h; required.')]
        ?string $by = null,
        #[Option('The stable identifier of who extends it, never an e-mail address; required.')]
        ?string $actor = null,
    ): int {
        if (null === $by || null === $actor) {
            $io->error('Give how much longer the incident lasts with --by, and who extends it with --actor.');

            return Command::INVALID;
        }

        try {
            $activation = $this->incidents->extend($id, DurationInput::parse($by), Actor::of($actor));
        } catch (IncidentModeException $incidentModeException) {
            $io->error($incidentModeException->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('The incident %s now ends at %s (UTC).', $activation->id, Moments::utc($activation->endsAt)));

        return Command::SUCCESS;
    }
}
