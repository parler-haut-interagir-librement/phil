<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Infrastructure\Monolog;

use Monolog\Logger;
use Override;
use Phil\ConsoleBundle\Application\Contract\DebugSwitch;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The debug logs of Monolog channels written to the console: a handler pushed on each named channel's logger for the
 * run, and taken off when it ends.
 *
 * The handlers it pushed are what it keeps between enable() and disable(); reset() takes them off too.
 */
final class ChannelDebugSwitch implements DebugSwitch, ResetInterface
{
    /**
     * @var list<array{Logger, OutputHandler}>
     */
    private array $pushed = [];

    /**
     * @param list<string> $channels The channels of the locator, in order
     */
    public function __construct(
        private readonly ContainerInterface $loggers,
        private readonly array $channels,
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
        $handler = new OutputHandler($output);

        foreach (array_intersect($this->channels, $channels) as $channel) {
            $logger = $this->loggers->get($channel);

            if ($logger instanceof Logger) {
                $logger->pushHandler($handler);
                $this->pushed[] = [$logger, $handler];
            }
        }
    }

    #[Override]
    public function disable(): void
    {
        foreach ($this->pushed as [$logger, $handler]) {
            $kept = [];

            foreach ($logger->getHandlers() as $one) {
                if ($one !== $handler) {
                    $kept[] = $one;
                }
            }

            $logger->setHandlers($kept);
        }

        $this->pushed = [];
    }

    #[Override]
    public function reset(): void
    {
        $this->disable();
    }
}
