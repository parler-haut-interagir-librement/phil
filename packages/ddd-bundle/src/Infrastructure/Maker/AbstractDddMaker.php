<?php

declare(strict_types=1);

namespace Phil\DddBundle\Infrastructure\Maker;

use InvalidArgumentException;
use Override;
use Phil\DddBundle\Application\Maker\DddSkeleton;
use Phil\DddBundle\Application\Maker\SkeletonFile;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

/**
 * What the `make:ddd:*` commands share: their arguments — the context, then the name for all but `make:ddd:context` —,
 * the files of `DddSkeleton` written under the root namespace of MakerBundle, and a refusal to overwrite a file.
 *
 * A missing argument is asked for by MakerBundle, with its description as the question.
 */
abstract class AbstractDddMaker extends AbstractMaker
{
    #[Override]
    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command->addArgument('context', InputArgument::OPTIONAL, 'The name of the bounded context (e.g. <fg=yellow>Catalog</>)');

        if (null !== $this->nameExample()) {
            $command->addArgument('name', InputArgument::OPTIONAL, \sprintf('The name of the %s (e.g. <fg=yellow>%s</>)', $this->what(), $this->nameExample()));
        }
    }

    #[Override]
    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }

    #[Override]
    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $context = $this->argument($input, 'context');
        $skeleton = new DddSkeleton(rtrim($generator->getRootNamespace(), '\\'));

        try {
            $files = $this->files($skeleton, $context, null === $this->nameExample() ? '' : $this->argument($input, 'name'));
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new RuntimeCommandException($invalidArgumentException->getMessage(), 0, $invalidArgumentException);
        }

        // Every file is checked before any is written: a refusal leaves the project as it was.
        foreach ($files as $file) {
            if (file_exists($generator->getRootDirectory().'/'.$file->path)) {
                throw new RuntimeCommandException(\sprintf('The file "%s" exists: it is not overwritten.', $file->path));
            }
        }

        foreach ($files as $file) {
            $generator->dumpFile($file->path, $file->contents);
        }

        $generator->writeChanges();

        $this->writeSuccessMessage($io);
        $io->text($this->next($skeleton, $input));
    }

    /**
     * The example of the name argument, or null when the command takes the context alone.
     */
    abstract protected function nameExample(): ?string;

    /**
     * What the name argument names, for its description.
     */
    abstract protected function what(): string;

    /**
     * @return list<SkeletonFile>
     *
     * @throws InvalidArgumentException when a name is refused
     */
    abstract protected function files(DddSkeleton $skeleton, string $context, string $name): array;

    /**
     * What to do next, shown after the files are written.
     *
     * @return list<string>
     */
    abstract protected function next(DddSkeleton $skeleton, InputInterface $input): array;

    protected function argument(InputInterface $input, string $name): string
    {
        $value = $input->getArgument($name);

        return \is_string($value) ? trim($value) : '';
    }
}
