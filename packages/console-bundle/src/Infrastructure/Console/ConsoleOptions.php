<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Infrastructure\Console;

use Phil\ConsoleBundle\Application\EnvironmentGuard;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * The options of the bundle, added to the running command before it runs.
 *
 * They are added to the command, not to the application: an application option would clash with a command that
 * defines an option of the same name its own way. Such an option stays the command's, and the bundle leaves it alone.
 */
final readonly class ConsoleOptions
{
    public const string TIMESTAMPS = 'timestamps';

    public const string DEBUG = 'debug-phil';

    /**
     * Adds the options, binds the input to them again, and returns the names of those that are the bundle's.
     *
     * @return list<string>
     */
    public function register(Command $command, InputInterface $input, bool $debug): array
    {
        $options = [
            [self::TIMESTAMPS, InputOption::VALUE_OPTIONAL, 'Prefix each output line with the time: "iso", "relative" or "none"', false],
            [EnvironmentGuard::FORCE_OPTION, InputOption::VALUE_NONE, 'Run a command that asks for a confirmation of the environment without asking', null],
        ];

        if ($debug) {
            $options[] = [self::DEBUG, InputOption::VALUE_OPTIONAL, 'Print the debug logs of the logger channels given, separated by commas, or of the configured ones', false];
        }

        // The native definition first: an invokable command declares its arguments and options there when it is empty.
        $native = $command->getNativeDefinition();
        $global = $command->getApplication()?->getDefinition();
        $owned = [];

        foreach ($options as [$name, $mode, $description, $default]) {
            if (!$native->hasOption($name) && !($global?->hasOption($name) ?? false)) {
                $command->addOption($name, null, $mode, $description, $default);
            }

            if ($native->hasOption($name) && new InputOption($name, null, $mode, $description, $default)->equals($native->getOption($name))) {
                $owned[] = $name;
            }
        }

        try {
            $input->bind($command->getDefinition());
        } catch (ExceptionInterface) {
            // The command checks its own arguments and options when it runs.
        }

        return $owned;
    }
}
