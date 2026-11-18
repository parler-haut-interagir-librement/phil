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
 * `phil:incident:start`: starts an incident from a preset, for a duration that is required and that the preset bounds,
 * in the name of an actor named by their stable identifier, never an e-mail address.
 */
#[AsCommand(name: 'phil:incident:start', description: 'Starts an incident from a preset, for a duration the preset bounds.')]
final readonly class StartIncidentCommand
{
    public function __construct(
        private IncidentMode $incidents,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The name of the preset, such as suspend-signups; phil:incident:list --presets lists them.')]
        string $preset,
        #[Option('How long the incident lasts, such as 90, 45m, 2h, 3d or 1d 12h; required.')]
        ?string $duration = null,
        #[Option('The stable identifier of who starts it, never an e-mail address; required.')]
        ?string $actor = null,
        #[Option('Why, kept in the journal.')]
        ?string $reason = null,
    ): int {
        if (null === $duration || null === $actor) {
            $io->error('Give how long the incident lasts with --duration, and who starts it with --actor.');

            return Command::INVALID;
        }

        try {
            $activation = $this->incidents->start($preset, DurationInput::parse($duration), Actor::of($actor), $reason);
        } catch (IncidentModeException $incidentModeException) {
            $io->error($incidentModeException->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('The incident %s suspends %s until %s (UTC).', $activation->id, implode(', ', $activation->restrictionNames()), Moments::utc($activation->endsAt)));

        return Command::SUCCESS;
    }
}
