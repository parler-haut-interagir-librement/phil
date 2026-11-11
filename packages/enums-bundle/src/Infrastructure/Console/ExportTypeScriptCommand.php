<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Infrastructure\Console;

use Override;
use Phil\EnumsBundle\Application\Translation\EnumTranslation;
use Phil\EnumsBundle\Application\TypeScript\TypeScriptExporter;
use Phil\EnumsBundle\Infrastructure\Discovery\EnumFinder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use UnitEnum;

/**
 * Writes enums as TypeScript — a constant object, a union type and the labels of each —, to a file or to the
 * output: every enum of the configured paths, or those named. With `--locale`, the labels of translatable enums
 * are translated in that locale. With `--check`, it writes nothing and fails when the file is not up to date, for
 * continuous integration.
 */
#[AsCommand(name: 'phil:enums:typescript', description: 'Writes the enums of the application as TypeScript')]
final class ExportTypeScriptCommand extends Command
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
            ->addArgument('enums', InputArgument::IS_ARRAY, 'The enum classes to write; every enum of the configured paths by default')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'The file to write; the standard output by default')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Translate the labels of translatable enums in this locale')
            ->addOption('check', null, InputOption::VALUE_NONE, 'Write nothing; fail when the output file differs from what would be written');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var list<string> $named */
        $named = $input->getArgument('enums');
        $unknown = array_filter($named, static fn (string $enum): bool => !enum_exists($enum));

        if ([] !== $unknown) {
            $io->error(\sprintf('Not an enum: %s.', implode(', ', $unknown)));

            return Command::FAILURE;
        }

        /** @var list<class-string<UnitEnum>> $enums */
        $enums = [] === $named ? EnumFinder::find($this->paths) : $named;
        $source = TypeScriptExporter::export($enums, $this->label($input->getOption('locale')));
        $file = $input->getOption('output');

        if (true === $input->getOption('check')) {
            return $this->check($io, $file, $source);
        }

        if (!\is_string($file)) {
            $output->write($source);

            return Command::SUCCESS;
        }

        if (false === @file_put_contents($file, $source)) {
            $io->error(\sprintf('Cannot write %s.', $file));

            return Command::FAILURE;
        }

        $io->success(\sprintf('%d enum(s) written to %s.', \count($enums), $file));

        return Command::SUCCESS;
    }

    private function check(SymfonyStyle $io, mixed $file, string $source): int
    {
        if (!\is_string($file)) {
            $io->error('--check compares with a file: give it with --output.');

            return Command::FAILURE;
        }

        if (!is_file($file) || file_get_contents($file) !== $source) {
            $io->error(\sprintf('%s is not up to date: run the command without --check.', $file));

            return Command::FAILURE;
        }

        $io->success(\sprintf('%s is up to date.', $file));

        return Command::SUCCESS;
    }

    /**
     * @return (callable(UnitEnum): string)|null
     */
    private function label(mixed $locale): ?callable
    {
        $translator = $this->translator;

        if (!\is_string($locale) || !$translator instanceof TranslatorInterface) {
            return null;
        }

        return static fn (UnitEnum $case): string => $case instanceof TranslatableInterface
            ? $case->trans($translator, $locale)
            : EnumTranslation::plainLabel($case);
    }
}
