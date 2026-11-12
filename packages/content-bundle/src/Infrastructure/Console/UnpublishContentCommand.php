<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Console;

use Override;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 *     bin/console phil:content:unpublish news:0192f3c4-… fr.
 */
#[AsCommand(name: 'phil:content:unpublish', description: 'Takes one locale of a content root offline')]
final class UnpublishContentCommand extends ContentCommand
{
    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('reference', InputArgument::REQUIRED, 'The content: "resource_key:identifier"')
            ->addArgument('locale', InputArgument::REQUIRED, 'The locale');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->attempt($input, $output, function (SymfonyStyle $io) use ($input): void {
            $reference = ContentReference::fromString($this->argument($input, 'reference'));
            $locale = $this->argument($input, 'locale');
            $this->contents->unpublish($reference, $locale);

            $io->success(\sprintf('Unpublished %s in %s.', $reference->toString(), $locale));
        });
    }
}
