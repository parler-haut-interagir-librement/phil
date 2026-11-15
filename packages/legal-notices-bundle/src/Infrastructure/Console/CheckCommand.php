<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle\Infrastructure\Console;

use Override;
use Phil\LegalNotices\LegalNotices;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * What the public documents lack — fields of the identity, bodies that cannot be rendered, placeholders without a
 * value —, one line each; it fails when there is any, so that a deployment can run it.
 */
#[AsCommand(name: 'phil:legal-notices:check', description: 'Lists what the public legal documents lack')]
final class CheckCommand extends Command
{
    public function __construct(
        private readonly LegalNotices $notices,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $problems = $this->notices->check();

        if ([] === $problems) {
            $io->success('The public legal documents lack nothing.');

            return Command::SUCCESS;
        }

        foreach ($problems as $problem) {
            $io->writeln(\sprintf('%s: %s', $problem->code, $problem->message));
        }

        $io->error(\sprintf('%d problem(s) found.', \count($problems)));

        return Command::FAILURE;
    }
}
