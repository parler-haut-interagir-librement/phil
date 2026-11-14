<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Console;

use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputOption;

/**
 * Gives every command a `--tenant` option, and runs the command in that tenant: `bin/console app:report
 * --tenant=retail`. A command line belongs to an operator, not to an account, so the tenant is not checked
 * against reachable tenants. Without the option the command runs in no tenant. A command that declares its own
 * `--tenant` keeps it.
 */
final readonly class TenantOption
{
    public const string NAME = 'tenant';

    public function __construct(
        private TenantContext $context,
    ) {
    }

    public function onCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();

        if (!$command instanceof Command || $command->getDefinition()->hasOption(self::NAME)) {
            return;
        }

        // Added to the command's own definition and to the one merged with the application's.
        $command->addOption(self::NAME, null, InputOption::VALUE_REQUIRED, 'The tenant the command acts in');

        $input = $event->getInput();
        $input->bind($command->getDefinition());

        $tenant = $input->getOption(self::NAME);

        if (\is_string($tenant)) {
            $this->context->enter(TenantId::fromString($tenant));
        }
    }

    public function onTerminate(): void
    {
        if ($this->context->current() instanceof TenantId) {
            $this->context->leave();
        }
    }
}
