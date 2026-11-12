<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Console;

use Override;
use Phil\ContentBundle\Application\ContentManager;
use Phil\ContentBundle\Infrastructure\Doctrine\ContentRoots;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 *     bin/console phil:content:list news.
 *
 * One line per locale of the latest draft version: the workflow place, the slug, and the version readers are
 * served.
 */
#[AsCommand(name: 'phil:content:list', description: 'Lists content roots and where each locale stands')]
final class ListContentCommand extends ContentCommand
{
    public function __construct(
        ContentManager $contents,
        private readonly ContentRoots $roots,
    ) {
        parent::__construct($contents);
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('resource-key', InputArgument::IS_ARRAY, 'The kinds of content to list; every declared kind when none is given');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->attempt($input, $output, function (SymfonyStyle $io) use ($input): void {
            $keys = $input->getArgument('resource-key');
            $keys = \is_array($keys) && [] !== $keys ? array_values(array_filter($keys, \is_string(...))) : $this->roots->resourceKeys();

            $lines = [];

            foreach ($keys as $key) {
                foreach ($this->contents->list($key) as $reference) {
                    $statuses = $this->contents->status($reference);

                    if ([] === $statuses) {
                        $lines[] = [$reference->toString(), '', '', '', '', ''];
                    }

                    foreach ($statuses as $status) {
                        $lines[] = [
                            $reference->toString(),
                            $status->locale,
                            (string) $status->draftVersion,
                            $status->place ?? '',
                            $status->slug ?? '',
                            null === $status->liveVersion ? '' : (string) $status->liveVersion,
                        ];
                    }
                }
            }

            $io->table(['Content', 'Locale', 'Draft', 'Place', 'Slug', 'Live'], $lines);
        });
    }
}
