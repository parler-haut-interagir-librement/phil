<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Console;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 *     bin/console phil:content:create news fr --file=news.yaml --field=slug=ouverture --publish.
 */
#[AsCommand(name: 'phil:content:create', description: 'Creates a content root and its first draft in one locale')]
final class CreateContentCommand extends ContentCommand
{
    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('resource-key', InputArgument::REQUIRED, 'The kind of content: "news", "static_page"')
            ->addArgument('locale', InputArgument::REQUIRED, 'The locale of the first draft: "fr", "fr_CA"')
            ->addOption('publish', null, InputOption::VALUE_NONE, 'Publish the draft once created');
        $this->addDataOptions();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->attempt($input, $output, function (SymfonyStyle $io) use ($input): void {
            $locale = $this->argument($input, 'locale');
            $reference = $this->contents->create($this->argument($input, 'resource-key'), $locale, $this->data($input));

            if (true === $input->getOption('publish')) {
                $this->contents->publish($reference, $locale);
            }

            $io->success(\sprintf('Created %s.', $reference->toString()));
            $io->writeln($reference->toString(), OutputInterface::VERBOSITY_QUIET);
        });
    }
}
