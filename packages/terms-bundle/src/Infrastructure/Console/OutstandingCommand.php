<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Infrastructure\Console;

use Override;
use Phil\TermsBundle\Application\Outstanding;
use Phil\TermsBundle\Domain\Exception\InvalidTerms;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:terms:outstanding terms [--latest] [--count].
 *
 * Lists, one identifier per line, the members of the member source who are not in order with a policy now — they
 * have not accepted the version required, or their acceptance lapsed —, or, with `--latest`, those who have not
 * accepted its latest material version yet. `--count` gives their number only.
 */
#[AsCommand(name: 'phil:terms:outstanding', description: 'Lists the members who have not accepted a policy')]
final class OutstandingCommand extends Command
{
    public function __construct(
        private readonly Outstanding $outstanding,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('policy', InputArgument::REQUIRED, 'The key of the policy')
            ->addOption('latest', null, InputOption::VALUE_NONE, 'The latest material version, whether it has taken effect or not')
            ->addOption('count', null, InputOption::VALUE_NONE, 'The number of members only');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $policy = $input->getArgument('policy');
        $policy = \is_string($policy) ? $policy : '';

        try {
            $members = true === $input->getOption('latest') ? $this->outstanding->ofLatestMaterial($policy) : $this->outstanding->at($policy, $this->clock->now());
        } catch (InvalidTerms $invalidTerms) {
            $output->writeln(\sprintf('<error>%s</error>', $invalidTerms->getMessage()));

            return Command::INVALID;
        }

        $count = 0;
        $listed = true !== $input->getOption('count');

        foreach ($members as $member) {
            ++$count;

            if ($listed) {
                $output->writeln($member, OutputInterface::OUTPUT_RAW);
            }
        }

        if (!$listed) {
            $output->writeln((string) $count);
        }

        return Command::SUCCESS;
    }
}
