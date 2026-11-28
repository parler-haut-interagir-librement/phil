<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Console;

use Phil\SitesConsoleBundle\Application\Console\SiteRegistry;
use Phil\SitesConsoleBundle\Domain\Exception\InvalidValue;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Registers a site, or changes the one with that identifier.
 */
#[AsCommand(name: 'phil:sites-console:site:add', description: 'Registers a site with the address of its agent and its public key')]
final readonly class SiteAddCommand
{
    public function __construct(
        private SiteRegistry $registry,
    ) {
    }

    /**
     * @param list<string> $group
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The identifier of the site, such as "shop"')]
        string $id,
        #[Argument(description: 'The address of its agent, such as https://shop.example.com/_sites-console')]
        string $url,
        #[Argument(description: 'The public key of its agent, from phil:sites-console:keys')]
        string $publicKey,
        #[Option(description: 'The name shown in the console')]
        ?string $name = null,
        #[Option(description: 'A group the site belongs to (repeatable)')]
        array $group = [],
        #[Option(description: 'The operator recorded in the journal')]
        ?string $actor = null,
    ): int {
        try {
            $site = $this->registry->register($id, $name ?? $id, $url, $publicKey, $group, $actor);
        } catch (InvalidValue $invalidValue) {
            $io->error($invalidValue->getMessage());

            return Command::INVALID;
        }

        $io->success(\sprintf('The site "%s" is registered at %s.', $site->id(), $site->agentUrl()->value));

        return Command::SUCCESS;
    }
}
