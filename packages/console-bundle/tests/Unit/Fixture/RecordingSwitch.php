<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Tests\Unit\Fixture;

use Override;
use Phil\ConsoleBundle\Application\Contract\DebugSwitch;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A debug switch that records what it is asked.
 */
final class RecordingSwitch implements DebugSwitch
{
    /**
     * @var list<string>
     */
    public array $calls = [];

    /**
     * @param list<string> $channels
     */
    public function __construct(
        private readonly array $channels = ['app', 'doctrine'],
    ) {
    }

    #[Override]
    public function channels(): array
    {
        return $this->channels;
    }

    #[Override]
    public function enable(array $channels, OutputInterface $output): void
    {
        $this->calls[] = 'enable '.json_encode($channels, \JSON_THROW_ON_ERROR);
        $output->writeln('debug on');
    }

    #[Override]
    public function disable(): void
    {
        $this->calls[] = 'disable';
    }
}
