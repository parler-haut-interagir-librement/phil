<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Infrastructure\Command;

use Phil\ConsoleBundle\Infrastructure\Console\CommandGuardReader;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Lists the commands guarded by `#[ForbidEnvironment]` and `#[ConfirmEnvironment]`, as a table or as JSON, and fails
 * when a command asks for a confirmation in an environment it is forbidden in.
 */
#[AsCommand('lint:console-guards', 'Lists the commands guarded by #[ForbidEnvironment] and #[ConfirmEnvironment]')]
final readonly class LintGuardsCommand
{
    public function __construct(
        private CommandGuardReader $reader,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        ?Application $application,
        #[Option('The output format: "txt" or "json"')]
        string $format = 'txt',
    ): int {
        if (!\in_array($format, ['txt', 'json'], true)) {
            throw new InvalidOptionException(\sprintf('The format "%s" is not supported: use "txt" or "json".', $format));
        }

        $rows = [];
        $contradictions = [];

        foreach ($application?->all() ?? [] as $name => $command) {
            $guards = $this->reader->guardsOf($command);

            if ($guards->isEmpty()) {
                continue;
            }

            $confirmed = $guards->confirmEvery ? ['*'] : $guards->confirmed;
            $rows[$name] = ['command' => $name, 'forbidden' => $guards->forbidden, 'confirmed' => $confirmed];
            $both = array_filter($guards->forbidden, $guards->asksConfirmationIn(...));

            if ([] !== $both) {
                $contradictions[] = \sprintf('The command "%s" asks for a confirmation in "%s", where it is forbidden.', $name, implode('", "', $both));
            }
        }

        ksort($rows);

        if ('json' === $format) {
            $io->writeln(json_encode(array_values($rows), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR), SymfonyStyle::OUTPUT_RAW);
        } elseif ([] === $rows) {
            $io->info('No command is guarded.');
        } else {
            $io->table(['Command', 'Forbidden in', 'Confirmation in'], array_map(
                static fn (array $row): array => [$row['command'], implode(', ', $row['forbidden']), implode(', ', $row['confirmed'])],
                $rows,
            ));
        }

        foreach ($contradictions as $contradiction) {
            $io->getErrorStyle()->error($contradiction);
        }

        return [] === $contradictions ? Command::SUCCESS : Command::FAILURE;
    }
}
