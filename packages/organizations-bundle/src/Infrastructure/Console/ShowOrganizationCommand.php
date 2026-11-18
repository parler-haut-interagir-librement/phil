<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Infrastructure\Console;

use DateTimeInterface;
use Override;
use Phil\OrganizationsBundle\Application\Contract\OrganizationStore;
use Phil\OrganizationsBundle\Application\Exception\UnknownOrganization;
use Phil\OrganizationsBundle\Application\Organizations;
use Phil\OrganizationsBundle\Application\OwnershipCheck;
use Phil\OrganizationsBundle\Application\RoleCatalog;
use Phil\OrganizationsBundle\Domain\Model\Invitation;
use Phil\OrganizationsBundle\Domain\Status\InvitationState;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:organizations:show acme.
 *
 * Shows an organization, by identifier or slug: its name, status and domain, how many members and owners it has —
 * with a warning under the owners recommended —, its teams and its usable invitations. Member identifiers are shown,
 * nothing else of them.
 */
#[AsCommand(name: 'phil:organizations:show', description: 'Shows an organization, its owners, teams and invitations')]
final class ShowOrganizationCommand extends Command
{
    public function __construct(
        private readonly Organizations $organizations,
        private readonly OrganizationStore $store,
        private readonly OwnershipCheck $ownership,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('organization', InputArgument::REQUIRED, 'The identifier or the slug of the organization');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $argument = $input->getArgument('organization');

        try {
            $organization = $this->organizations->find(\is_string($argument) ? $argument : '');
        } catch (UnknownOrganization $unknownOrganization) {
            $output->writeln($unknownOrganization->getMessage());

            return Command::FAILURE;
        }

        $owners = $this->ownership->owners($organization->id);
        $now = $this->clock->now();
        $usable = array_filter($this->store->invitations($organization->id), static fn (Invitation $invitation): bool => InvitationState::Usable === $invitation->stateAt($now));

        $output->writeln([
            \sprintf('Identifier: %s', $organization->id->value),
            \sprintf('Slug:       %s', $organization->slug->value),
            \sprintf('Name:       %s', $organization->name->value),
            \sprintf('Status:     %s', $organization->status->value),
            \sprintf('Created:    %s', $organization->createdAt->format(DateTimeInterface::ATOM)),
            \sprintf('Domain:     %s', $organization->emailDomain->value ?? '-'),
            \sprintf('Members:    %d', $this->store->countMembers($organization->id)),
            \sprintf('Owners:     %d', $owners),
            \sprintf('Teams:      %d', \count($this->store->teams($organization->id))),
            \sprintf('Invitations usable: %d', \count($usable)),
        ]);

        foreach ($this->store->memberships($organization->id, 100) as $membership) {
            if ($membership->roles->has(RoleCatalog::OWNER)) {
                $output->writeln(\sprintf('  owner %s', $membership->memberId->value));
            }
        }

        if ($owners < $this->ownership->recommended) {
            $output->writeln(\sprintf('<comment>Warning: %d owners, %d recommended, so that the organization is never left without one.</comment>', $owners, $this->ownership->recommended));
        }

        return Command::SUCCESS;
    }
}
