<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Infrastructure\Console;

use Override;
use Phil\AuditBundle\Application\TrailVerification;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:audit:verify [segment…] [--tenant=reference] [--accept-explained].
 *
 * Verifies the trail — or, with `--tenant`, the segments of that site only — and fails loudly: every break on a line
 * of its own, and a failure code that an alert can watch. An explained break — an anchored head a covered
 * discontinuity accounts for — fails too, until someone has reviewed it: `--accept-explained` then lets the command
 * succeed when every break is explained. A break that no discontinuity explains fails whatever the options.
 */
#[AsCommand(name: 'phil:audit:verify', description: 'Verifies the hash chain of the audit trail against its anchors')]
final class VerifyCommand extends Command
{
    public function __construct(
        private readonly TrailVerification $verification,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('segments', InputArgument::IS_ARRAY, 'The segments to verify; every one when none is given');
        $this->addOption('tenant', null, InputOption::VALUE_REQUIRED, 'Only the segments of the site of this tenant');
        $this->addOption('accept-explained', null, InputOption::VALUE_NONE, 'Succeed when every break is explained by a covered discontinuity, once they were reviewed');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $segments */
        $segments = $input->getArgument('segments');
        $tenant = $input->getOption('tenant');
        $broken = 0;
        $explained = 0;
        $verified = $this->verification->verify([] === $segments ? null : $segments, \is_string($tenant) ? $tenant : null);

        if ([] === $verified) {
            $output->writeln('No segment to verify.');
        }

        foreach ($verified as $segment => $breaks) {
            $alarms = 0;

            foreach ($breaks as $break) {
                $output->writeln($break->toString());
                $alarms += $break->isAlarm() ? 1 : 0;
            }

            $broken += $alarms;
            // Read only when no segment is broken, where every break counted is an explained one.
            $explained += \count($breaks);
            $output->writeln(\sprintf('%s: %s', $segment, match (true) {
                $alarms > 0 => 'BROKEN',
                [] !== $breaks => \sprintf('holds, with %d explained break(s) to review', \count($breaks)),
                default => 'holds',
            }));
        }

        if ($broken > 0) {
            $output->writeln(\sprintf('%d break(s): the audit trail does not hold.', $broken));

            return Command::FAILURE;
        }

        if ($explained > 0 && true !== $input->getOption('accept-explained')) {
            $output->writeln(\sprintf('%d explained break(s) to review: run again with --accept-explained once they were.', $explained));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
