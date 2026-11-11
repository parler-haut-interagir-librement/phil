<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Tests\Unit\Fixture;

use Phil\ConsoleBundle\Domain\Attribute\ConfirmEnvironment;
use Phil\ConsoleBundle\Domain\Attribute\ForbidEnvironment;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;

#[ConfirmEnvironment('test')]
final readonly class MethodCommands
{
    public function __construct(
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[AsCommand('test:reset')]
    #[ForbidEnvironment('prod')]
    public function reset(): int
    {
        return 0;
    }

    #[AsCommand('test:log')]
    public function log(): int
    {
        $this->logger?->debug('A <b>debug</b> message');

        return 0;
    }
}
