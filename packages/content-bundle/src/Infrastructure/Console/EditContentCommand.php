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
 *     bin/console phil:content:edit news:0192f3c4-… fr --field=excerptTitle="Nouveau titre".
 *
 * A published version is never written: the edit forks the next version, and the locale moves to `draft`.
 */
#[AsCommand(name: 'phil:content:edit', description: 'Edits the draft of one locale of a content root')]
final class EditContentCommand extends ContentCommand
{
    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('reference', InputArgument::REQUIRED, 'The content: "resource_key:identifier"')
            ->addArgument('locale', InputArgument::REQUIRED, 'The locale of the draft');
        $this->addDataOptions();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->attempt($input, $output, function (SymfonyStyle $io) use ($input): void {
            $reference = ContentReference::fromString($this->argument($input, 'reference'));
            $this->contents->edit($reference, $this->argument($input, 'locale'), $this->data($input));

            $io->success(\sprintf('Edited the draft of %s.', $reference->toString()));
        });
    }
}
