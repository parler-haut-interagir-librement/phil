<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Console;

use Phil\SitesConsoleBundle\Application\Console\SiteRegistry;
use Phil\SitesConsoleBundle\Application\Exception\NotFound;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Removes a site and its snapshots; its lines of the journal stay.
 */
#[AsCommand(name: 'phil:sites-console:site:remove', description: 'Removes a site and its snapshots')]
final readonly class SiteRemoveCommand
{
    public function __construct(
        private SiteRegistry $registry,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The identifier of the site')]
        string $id,
        #[Option(description: 'The operator recorded in the journal')]
        ?string $actor = null,
    ): int {
        try {
            $this->registry->remove($id, $actor);
        } catch (NotFound $notFound) {
            $io->error($notFound->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('The site "%s" is removed.', $id));

        return Command::SUCCESS;
    }
}
