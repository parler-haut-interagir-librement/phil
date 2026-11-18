<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Infrastructure\Console;

use Override;
use Phil\OrganizationsBundle\Application\OwnershipCheck;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:organizations:check-owners.
 *
 * Lists the organizations with fewer owners than recommended, those without any first. It fails when an organization
 * has no owner at all — the last owner's data was erased — so that a scheduled run alerts the operator, who names a new
 * owner; fewer owners than recommended is a warning only.
 */
#[AsCommand(name: 'phil:organizations:check-owners', description: 'Lists the organizations with fewer owners than recommended')]
final class CheckOwnersCommand extends Command
{
    public function __construct(
        private readonly OwnershipCheck $ownership,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'How many, 1 to 1000', '100');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = filter_var($input->getOption('limit'), \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);

        if (!\is_int($limit)) {
            $output->writeln('The limit is a whole number from 1 to 1000.');

            return Command::INVALID;
        }

        $found = $this->ownership->belowRecommendation($limit);

        foreach ($found as $organization => $owners) {
            $output->writeln(\sprintf('%s: %d of %d owners recommended', $organization, $owners, $this->ownership->recommended));
        }

        if ([] === $found) {
            $output->writeln(\sprintf('Every organization has at least %d owners.', $this->ownership->recommended));
        }

        return \in_array(0, $found, true) ? Command::FAILURE : Command::SUCCESS;
    }
}
