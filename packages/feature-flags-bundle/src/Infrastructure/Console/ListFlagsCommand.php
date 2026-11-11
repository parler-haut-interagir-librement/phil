<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Infrastructure\Console;

use DateTimeImmutable;
use Override;
use Phil\FeatureFlagsBundle\Application\Flag\CatalogEntry;
use Phil\FeatureFlagsBundle\Application\Flag\FlagCatalog;
use Phil\FeatureFlagsBundle\Application\Flag\UnreadableEntry;
use Phil\FeatureFlagsBundle\Domain\Flag\Experiment;
use Phil\FeatureFlagsBundle\Domain\Flag\Rule\Rule;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The current configuration of the flags: each flag with its state, kind, targeting, owner, expiry and source.
 *
 * A flag whose definition its provider cannot read is named after the table with its reason; it does not fail
 * the command.
 *
 * `--fail-on-expired` exits with a failure when a flag is past its expiry date, so that a CI job turns a
 * forgotten flag into a red build rather than into one more branch in the code.
 */
#[AsCommand(name: 'phil:feature-flags:list', description: 'Lists the feature flags with their current configuration.')]
final class ListFlagsCommand extends Command
{
    public function __construct(
        private readonly FlagCatalog $catalog,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('expired', null, InputOption::VALUE_NONE, 'Lists only the flags past their expiry date.')
            ->addOption('fail-on-expired', null, InputOption::VALUE_NONE, 'Exits with a failure when a flag is past its expiry date.');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = $this->clock->now();
        $entries = $this->catalog->entries();
        $expired = array_filter($entries, static fn (CatalogEntry $entry): bool => $entry->declared->metadata->isExpiredAt($now));
        $shown = true === $input->getOption('expired') ? $expired : $entries;

        $io->table(
            ['Flag', 'State', 'Kind', 'Targeting', 'Owner', 'Expires on', 'Source'],
            array_map(fn (CatalogEntry $entry): array => $this->row($entry, $now), $shown),
        );

        $unreadable = $this->catalog->unreadable();

        if ([] !== $unreadable) {
            $io->warning(\sprintf(
                '%d flag%s cannot be read: %s',
                \count($unreadable),
                1 === \count($unreadable) ? '' : 's',
                implode('; ', array_map(static fn (UnreadableEntry $entry): string => \sprintf('%s (%s): %s', $entry->name, $entry->source, $entry->reason), $unreadable)),
            ));
        }

        if ([] === $expired) {
            return Command::SUCCESS;
        }

        $message = \sprintf('%d flag%s past %s expiry date: %s.', \count($expired), 1 === \count($expired) ? ' is' : 's are', 1 === \count($expired) ? 'its' : 'their', implode(', ', array_map(static fn (CatalogEntry $entry): string => $entry->declared->name->value, $expired)));

        if (true === $input->getOption('fail-on-expired')) {
            $io->error($message);

            return Command::FAILURE;
        }

        $io->warning($message);

        return Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function row(CatalogEntry $entry, DateTimeImmutable $now): array
    {
        $flag = $entry->effective();
        $metadata = $flag->metadata;

        return [
            $flag->name->value,
            ($flag->active ? 'active' : 'inactive').(null === $entry->stored ? '' : ' (stored)'),
            $metadata->kind->value ?? '',
            $this->targeting($flag->rules, $flag->experiment),
            $metadata->owner ?? '',
            $metadata->expiresOn instanceof DateTimeImmutable ? $metadata->expiresOn->format('Y-m-d').($metadata->isExpiredAt($now) ? ' (expired)' : '') : '',
            $entry->source,
        ];
    }

    /**
     * @param list<Rule> $rules
     */
    private function targeting(array $rules, ?Experiment $experiment): string
    {
        $parts = [] === $rules ? ['everyone'] : [\sprintf('%d rule%s', \count($rules), 1 === \count($rules) ? '' : 's')];

        if ($experiment instanceof Experiment) {
            $variants = [];

            foreach ($experiment->variants as $variant) {
                $variants[] = $variant->name->value.' '.$variant->weight.'%';
            }

            $parts[] = 'A/B by '.$experiment->attribute->value.': '.implode(', ', $variants);
        }

        return implode('; ', $parts);
    }
}
