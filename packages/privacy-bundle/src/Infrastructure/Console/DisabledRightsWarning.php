<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Console;

use Phil\PrivacyBundle\Application\PrivacyRights;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

/**
 * Before every command, on the error output so that a command's result stays clean: a right of the members is turned off
 * on this site.
 */
final readonly class DisabledRightsWarning
{
    public function __construct(
        private PrivacyRights $rights,
    ) {
    }

    public function __invoke(ConsoleCommandEvent $event): void
    {
        $output = $event->getOutput();

        if ($output instanceof ConsoleOutputInterface) {
            $output = $output->getErrorOutput();
        }

        foreach ($this->rights->warnings() as $warning) {
            $output->writeln(['', '<error> DANGER: '.$warning.' </error>', '']);
        }
    }
}
