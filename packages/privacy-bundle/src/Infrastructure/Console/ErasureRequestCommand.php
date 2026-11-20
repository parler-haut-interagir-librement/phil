<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Console;

use Override;
use Phil\PrivacyBundle\Application\Erasure\ErasureRequests;
use Phil\PrivacyBundle\Domain\Erasure\ErasureProgress;
use Phil\PrivacyBundle\Domain\Erasure\ErasureStep;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:privacy:erasure 3f2a…
 *     bin/console phil:privacy:erasure 3f2a… --replay
 *     bin/console phil:privacy:erasure 3f2a… --fail --confirm.
 *
 * Shows an erasure request module by module; `--replay` sends again the modules that need attention or wait for an
 * acknowledgement, and brings the request back to running; `--fail --confirm` closes it as failed, an operator's
 * decision. Nothing names the person.
 */
#[AsCommand(name: 'phil:privacy:erasure', description: 'Shows, replays or closes as failed an erasure request')]
final class ErasureRequestCommand extends Command
{
    public function __construct(
        private readonly ErasureRequests $requests,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::REQUIRED, 'The identifier of the erasure request')
            ->addOption('replay', null, InputOption::VALUE_NONE, 'Send again the modules that need attention or wait for an acknowledgement')
            ->addOption('fail', null, InputOption::VALUE_NONE, 'Close the request as failed, with --confirm')
            ->addOption('confirm', null, InputOption::VALUE_NONE, 'Confirm --fail: without it, nothing is closed');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getArgument('id');
        $id = \is_string($id) ? $id : '';

        $progress = $this->requests->progress($id);

        if (!$progress instanceof ErasureProgress) {
            $output->writeln('No erasure request has this identifier.');

            return Command::FAILURE;
        }

        if (true === $input->getOption('replay')) {
            $modules = $this->requests->replay($id);
            $output->writeln([] === $modules ? 'Nothing to replay.' : 'Replayed: '.implode(', ', $modules).'.');
        } elseif (true === $input->getOption('fail')) {
            if (true !== $input->getOption('confirm')) {
                $output->writeln('Nothing was closed: add --confirm to close the request as failed.');

                return Command::INVALID;
            }

            $output->writeln($this->requests->fail($id) ? 'Closed as failed.' : 'The request is already closed.');
        }

        $this->show($output, $this->requests->progress($id) ?? $progress);

        return Command::SUCCESS;
    }

    private function show(OutputInterface $output, ErasureProgress $progress): void
    {
        $counts = $progress->counts();
        $output->writeln(\sprintf('%s: %s, %d of %d modules completed, inventory %s.', $progress->request->id, $progress->request->state->value, $counts['completed'], $counts['total'], $progress->request->inventoryVersion));

        foreach ($progress->steps as $step) {
            $output->writeln(\sprintf('- %s: %s%s%s', $step->module, $step->state->value, null === $step->reason ? '' : ' ('.$step->reason->value.')', $this->effects($step)));
        }
    }

    private function effects(ErasureStep $step): string
    {
        if ([] === $step->effects) {
            return $step->attempts > 0 ? \sprintf(', %d failed attempts', $step->attempts) : '';
        }

        $effects = [];

        foreach ($step->effects as $store => $effect) {
            $effects[] = $store.' '.$effect->value.($effect->leavesPersonalData() ? ' (personal data left)' : '');
        }

        return ', '.implode(', ', $effects);
    }
}
