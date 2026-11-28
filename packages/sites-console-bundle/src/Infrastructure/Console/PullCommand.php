<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Console;

use Phil\SitesConsoleBundle\Application\Console\Puller;
use Phil\SitesConsoleBundle\Application\Console\SiteRegistry;
use Phil\SitesConsoleBundle\Application\Exception\NotFound;
use Phil\SitesConsoleBundle\Domain\Model\Site;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Reads the versions and the health of the sites now, one after the other, and shows what each answered.
 */
#[AsCommand(name: 'phil:sites-console:pull', description: 'Reads the versions and the health of the sites')]
final readonly class PullCommand
{
    public function __construct(
        private SiteRegistry $registry,
        private Puller $puller,
    ) {
    }

    /**
     * @param list<string> $site
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'A site to read (repeatable); every site when none')]
        array $site = [],
        #[Option(description: 'The sites of a group')]
        ?string $group = null,
    ): int {
        try {
            $sites = $this->registry->select($site, $group);
        } catch (NotFound $notFound) {
            $io->error($notFound->getMessage());

            return Command::FAILURE;
        }

        $rows = [];
        $failed = false;

        foreach (array_map(static fn (Site $one): string => $one->id(), $sites) as $siteId) {
            $snapshot = $this->puller->pull($siteId);
            $failed = $failed || !$snapshot->isReachable();
            $rows[] = [$siteId, $snapshot->isReachable() ? $snapshot->health()->state->value : 'unreachable', $snapshot->php() ?? '', $snapshot->symfony() ?? '', $snapshot->error() ?? ''];
        }

        $io->table(['Site', 'Health', 'PHP', 'Symfony', 'Error'], $rows);

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
