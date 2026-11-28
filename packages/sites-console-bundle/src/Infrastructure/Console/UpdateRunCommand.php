<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Console;

use Phil\SitesConsoleBundle\Application\Console\UpdatePlans;
use Phil\SitesConsoleBundle\Application\Exception\NotFound;
use Phil\SitesConsoleBundle\Domain\Status\PlanState;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Runs an update plan, every batch left or that many, and prints its report per site.
 */
#[AsCommand(name: 'phil:sites-console:update:run', description: 'Runs an update plan and prints its report')]
final readonly class UpdateRunCommand
{
    public function __construct(
        private UpdatePlans $plans,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The identifier of the plan')]
        string $plan,
        #[Option(description: 'The batches to run; every batch left when none')]
        ?int $batches = null,
        #[Option(description: 'The operator recorded in the journal')]
        ?string $actor = null,
    ): int {
        try {
            $run = $this->plans->run($plan, $batches, $actor);
        } catch (NotFound $notFound) {
            $io->error($notFound->getMessage());

            return Command::FAILURE;
        }

        $rows = [];

        foreach ($run->steps() as $step) {
            $rows[] = [(string) $step->batch, $step->siteId, $step->outcome->value, $step->message];
        }

        $io->writeln(\sprintf('Plan %s: %s %s, %s', $run->id(), $run->package(), $run->version(), $run->state()->value));
        $io->table(['Batch', 'Site', 'Outcome', 'Message'], $rows);

        return PlanState::Stopped === $run->state() ? Command::FAILURE : Command::SUCCESS;
    }
}
