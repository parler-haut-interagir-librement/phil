<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\Console;

use Override;
use Phil\ApiContract\Deprecation\ObservedDeprecations;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:api-contract:deprecations.
 *
 * Lists the deprecated endpoints this application calls, and fails while one of them has a sunset: run in CI, it
 * tells the team before the endpoint goes away.
 */
#[AsCommand(name: 'phil:api-contract:deprecations', description: 'Lists the deprecated endpoints this application calls')]
final class DeprecationsCommand extends Command
{
    public function __construct(
        private readonly ObservedDeprecations $observed,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = $this->clock->now();
        $signals = $this->observed->all();
        ksort($signals);
        $sunset = false;

        foreach ($signals as $endpoint => $signal) {
            if (null !== $signal->sunsetAt) {
                $sunset = true;
            }

            $output->writeln(\sprintf(
                '%s: %s%s%s',
                $endpoint,
                null === $signal->deprecatedAt ? 'deprecation announced' : ($signal->isDeprecatedAt($now) ? 'deprecated since ' : 'deprecated from ').$signal->deprecatedAt->format('Y-m-d'),
                null === $signal->sunsetAt ? '' : ($signal->isSunsetAt($now) ? ', gone since ' : ', goes away on ').$signal->sunsetAt->format('Y-m-d'),
                null === $signal->link ? '' : ' — '.$signal->link,
            ));
        }

        if ([] === $signals) {
            $output->writeln('No deprecated endpoint was called.');
        }

        return $sunset ? Command::FAILURE : Command::SUCCESS;
    }
}
