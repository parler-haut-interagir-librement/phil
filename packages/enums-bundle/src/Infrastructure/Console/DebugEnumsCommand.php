<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Infrastructure\Console;

use BackedEnum;
use Override;
use Phil\Enums\Attribute\Group;
use Phil\Enums\Attribute\Weight;
use Phil\Enums\Collection\CaseCollection;
use Phil\Enums\Meta\Reflection;
use Phil\EnumsBundle\Application\Translation\EnumTranslation;
use Phil\EnumsBundle\Infrastructure\Discovery\EnumFinder;
use ReflectionEnum;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use UnitEnum;

/**
 * Lists the enums of the configured paths, or describes one: its cases with their values, labels, descriptions,
 * groups, weights, default and deprecated marks, the required metadata they miss, and — with `--locale` — the
 * translations each key lacks in that locale.
 */
#[AsCommand(name: 'debug:enums', description: 'Lists the enums of the application, or describes the cases of one')]
final class DebugEnumsCommand extends Command
{
    /**
     * @param list<string> $paths
     */
    public function __construct(
        private readonly array $paths,
        private readonly ?TranslatorInterface $translator = null,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('enum', InputArgument::OPTIONAL, 'The enum to describe: its class, or its short name')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Check the translations of the enum in this locale');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $enums = EnumFinder::find($this->paths);
        $name = $input->getArgument('enum');

        if (!\is_string($name)) {
            $io->table(['Enum', 'Backing', 'Cases', 'Translatable'], array_map(static fn (string $enum): array => [
                $enum,
                new ReflectionEnum($enum)->getBackingType()?->getName() ?? 'pure',
                \count($enum::cases()),
                is_subclass_of($enum, TranslatableInterface::class) ? 'yes' : 'no',
            ], $enums));

            return Command::SUCCESS;
        }

        $matches = array_values(array_filter($enums, static fn (string $enum): bool => $enum === ltrim($name, '\\') || str_ends_with($enum, '\\'.$name)));

        if (1 !== \count($matches)) {
            $io->error([] === $matches ? \sprintf('No enum named "%s" in the configured paths.', $name) : \sprintf('"%s" names several enums: %s.', $name, implode(', ', $matches)));

            return Command::FAILURE;
        }

        return $this->describe($io, $matches[0], $input->getOption('locale'));
    }

    /**
     * @param class-string<UnitEnum> $enum
     */
    private function describe(SymfonyStyle $io, string $enum, mixed $locale): int
    {
        $io->title($enum);
        $default = Reflection::defaultCase($enum);

        $io->table(['Case', 'Value', 'Label', 'Description', 'Groups', 'Weight', 'Marks'], array_map(static fn (UnitEnum $case): array => [
            $case->name,
            $case instanceof BackedEnum ? $case->value : '',
            EnumTranslation::plainLabel($case),
            EnumTranslation::descriptionKey($case) ?? '',
            implode(', ', array_keys(CaseCollection::of([$case])->groupByMeta(Group::class))),
            json_encode(Reflection::metaValue(Weight::class, $case), \JSON_THROW_ON_ERROR),
            implode(', ', array_filter([$case === $default ? 'default' : null, Reflection::isDeprecated($case) ? 'deprecated' : null])),
        ], $enum::cases()));

        $failed = false;

        foreach (Reflection::missingRequiredMeta($enum) as $case => $missing) {
            $io->warning(\sprintf('%s misses the required %s.', $case, implode(', ', $missing)));
            $failed = true;
        }

        if (\is_string($locale)) {
            $failed = $this->checkTranslations($io, $enum, $locale) || $failed;
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Whether a key of the enum lacks its translation in the locale.
     *
     * @param class-string<UnitEnum> $enum
     */
    private function checkTranslations(SymfonyStyle $io, string $enum, string $locale): bool
    {
        if (!is_subclass_of($enum, TranslatableInterface::class)) {
            $io->note(\sprintf('%s is not translatable.', $enum));

            return false;
        }

        if (!$this->translator instanceof TranslatorBagInterface) {
            $io->note('No translator with catalogues: the translations cannot be checked.');

            return false;
        }

        $catalogue = $this->translator->getCatalogue($locale);
        $missing = [];

        foreach ($enum::cases() as $case) {
            foreach ([EnumTranslation::labelKey($case), EnumTranslation::descriptionKey($case)] as $key) {
                if (null !== $key && !$catalogue->defines($key, EnumTranslation::domain($case))) {
                    $missing[] = [$case->name, $key, EnumTranslation::domain($case)];
                }
            }
        }

        if ([] === $missing) {
            $io->success(\sprintf('Every key of %s is translated in "%s".', $enum, $locale));

            return false;
        }

        $io->table(['Case', 'Missing key', 'Domain'], $missing);
        $io->error(\sprintf('%d key(s) of %s have no translation in "%s".', \count($missing), $enum, $locale));

        return true;
    }
}
