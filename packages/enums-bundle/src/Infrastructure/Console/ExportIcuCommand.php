<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Infrastructure\Console;

use LogicException;
use Override;
use Phil\EnumsBundle\Application\Icu\IcuSelect;
use Phil\EnumsBundle\Application\Translation\EnumTranslation;
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
 * Writes an ICU `select` message over the cases of an enum, to paste in a catalogue of the `+intl-icu` format and
 * edit branch by branch. With `--locale`, each branch starts from the case's translated label.
 */
#[AsCommand(name: 'phil:enums:icu', description: 'Writes an ICU select message over the cases of an enum')]
final class ExportIcuCommand extends Command
{
    public function __construct(
        private readonly ?TranslatorInterface $translator = null,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('enum', InputArgument::REQUIRED, 'The enum class')
            ->addOption('argument', null, InputOption::VALUE_REQUIRED, 'The name of the message argument', 'value')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Start each branch from the label translated in this locale');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $enum = $input->getArgument('enum');
        $argument = $input->getOption('argument');

        if (!\is_string($enum) || !enum_exists($enum)) {
            new SymfonyStyle($input, $output)->error(\sprintf('Not an enum: %s.', \is_string($enum) ? $enum : ''));

            return Command::FAILURE;
        }

        $locale = $input->getOption('locale');
        $translator = $this->translator;
        $text = \is_string($locale) && $translator instanceof TranslatorInterface
            ? static fn (UnitEnum $case): string => $case instanceof TranslatableInterface ? $case->trans($translator, $locale) : EnumTranslation::plainLabel($case)
            : null;

        try {
            $message = IcuSelect::of($enum, \is_string($argument) ? $argument : 'value', $text);
        } catch (LogicException $logicException) {
            new SymfonyStyle($input, $output)->error($logicException->getMessage());

            return Command::FAILURE;
        }

        $output->writeln($message);

        return Command::SUCCESS;
    }
}
