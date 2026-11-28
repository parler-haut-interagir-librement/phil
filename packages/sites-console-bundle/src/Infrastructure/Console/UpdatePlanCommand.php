<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Console;

use Phil\SitesConsoleBundle\Application\Console\SiteRegistry;
use Phil\SitesConsoleBundle\Application\Console\UpdatePlans;
use Phil\SitesConsoleBundle\Application\Exception\NotFound;
use Phil\SitesConsoleBundle\Domain\Exception\InvalidValue;
use Phil\SitesConsoleBundle\Domain\Model\Site;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Plans an update of a package on sites, in batches; `phil:sites-console:update:run` runs it.
 */
#[AsCommand(name: 'phil:sites-console:update:plan', description: 'Plans an update of a package on several sites, in batches')]
final readonly class UpdatePlanCommand
{
    public function __construct(
        private SiteRegistry $registry,
        private UpdatePlans $plans,
    ) {
    }

    /**
     * @param list<string> $site
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The package, such as vendor/package')]
        string $package,
        #[Argument(description: 'The version to bring it to')]
        string $version,
        #[Option(description: 'A site to update, in order (repeatable); every site when none')]
        array $site = [],
        #[Option(description: 'The sites of a group')]
        ?string $group = null,
        #[Option(description: 'The sites per batch')]
        int $batchSize = 1,
        #[Option(description: 'The operator recorded in the journal')]
        ?string $actor = null,
    ): int {
        try {
            $sites = array_map(static fn (Site $one): string => $one->id(), $this->registry->select($site, $group));
            $plan = $this->plans->plan($package, $version, $sites, $batchSize, $actor);
        } catch (InvalidValue|NotFound $error) {
            $io->error($error->getMessage());

            return Command::INVALID;
        }

        $io->success(\sprintf('Plan %s: %s %s on %d sites. Run it with phil:sites-console:update:run %s', $plan->id(), $package, $version, \count($sites), $plan->id()));

        return Command::SUCCESS;
    }
}
