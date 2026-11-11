<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Infrastructure\Maker;

use InvalidArgumentException;
use Override;
use Phil\EnumsBundle\Application\Maker\EnumBlueprint;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * `make:enum`: a new enum in `Enum\`, with the traits of `phil/enums`, a `Label` on each case and, when asked, the
 * translation of `phil/enums-bundle`:
 *
 * ```console
 * $ bin/console make:enum OrderStatus --case=Pending --case=InTransit --translatable
 * ```
 *
 * Without `--case`, the command asks for the cases one by one until an empty answer.
 */
final class MakeEnum extends AbstractMaker
{
    #[Override]
    public static function getCommandName(): string
    {
        return 'make:enum';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a new enum with the traits and labels of phil/enums';
    }

    #[Override]
    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('name', InputArgument::OPTIONAL, 'The name of the enum (e.g. <fg=yellow>OrderStatus</>)')
            ->addOption('backed', null, InputOption::VALUE_REQUIRED, 'string, int or pure', EnumBlueprint::STRING)
            ->addOption('case', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A case name; repeat the option for each case')
            ->addOption('translatable', null, InputOption::VALUE_NONE, 'Make the enum translatable by its labels');
    }

    #[Override]
    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        if ([] !== $input->getOption('case')) {
            return;
        }

        $cases = [];

        while (\is_string($case = $io->ask('A case name (empty to finish)'))) {
            $cases[] = $case;
        }

        $input->setOption('case', $cases);
    }

    #[Override]
    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $name = $input->getArgument('name');
        $backing = $input->getOption('backed');

        /** @var list<string> $cases */
        $cases = $input->getOption('case');

        try {
            $blueprint = EnumBlueprint::of(\is_string($backing) ? $backing : '', $cases, true === $input->getOption('translatable'));
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new RuntimeCommandException($invalidArgumentException->getMessage(), 0, $invalidArgumentException);
        }

        $class = $generator->createClassNameDetails(\is_string($name) ? $name : '', 'Enum\\');
        $namespace = substr($class->getFullName(), 0, (int) strrpos($class->getFullName(), '\\'));
        $generator->generateClass($class->getFullName(), \dirname(__DIR__, 3).'/templates/maker/Enum.tpl.php', ['source' => $blueprint->source($namespace, $class->getShortName())]);
        $generator->writeChanges();

        $this->writeSuccessMessage($io);
        $io->text('Next: add the metadata your enum needs — groups, descriptions, transitions —, and its translations.');
    }

    #[Override]
    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }
}
