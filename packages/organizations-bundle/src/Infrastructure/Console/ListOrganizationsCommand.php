<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Infrastructure\Console;

use Override;
use Phil\OrganizationsBundle\Application\Organizations;
use Phil\OrganizationsBundle\Domain\Status\OrganizationStatus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:organizations:list --status=active --limit=50 --after=acme.
 *
 * Lists the organizations by slug, a page at a time: identifier, slug, name, status, verified domain.
 */
#[AsCommand(name: 'phil:organizations:list', description: 'Lists the organizations')]
final class ListOrganizationsCommand extends Command
{
    public function __construct(
        private readonly Organizations $organizations,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'active or archived; both when omitted')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'How many, 1 to 1000', '100')
            ->addOption('after', null, InputOption::VALUE_REQUIRED, 'The slug the page starts after');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $option = $input->getOption('status');
        $status = \is_string($option) ? OrganizationStatus::tryFrom($option) : null;
        $limit = filter_var($input->getOption('limit'), \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        $after = $input->getOption('after');

        if (!\is_int($limit) || (null !== $option && null === $status)) {
            $output->writeln('The status is "active" or "archived", the limit a whole number from 1 to 1000.');

            return Command::INVALID;
        }

        $table = new Table($output)->setHeaders(['Identifier', 'Slug', 'Name', 'Status', 'Domain']);

        foreach ($this->organizations->list($status, $limit, \is_string($after) ? $after : null) as $organization) {
            $table->addRow([$organization->id->value, $organization->slug->value, $organization->name->value, $organization->status->value, $organization->emailDomain->value ?? '']);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
