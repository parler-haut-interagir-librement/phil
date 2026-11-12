<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Console;

use Phil\ContentBundle\Application\ContentManager;
use Phil\DddBundle\Domain\Exception\DomainError;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * What the content commands share: the manager, the options of submitted data, and a refusal of the domain
 * reported as an error message rather than a stack trace.
 */
abstract class ContentCommand extends Command
{
    public function __construct(
        protected readonly ContentManager $contents,
    ) {
        parent::__construct();
    }

    /**
     * Runs the command's work; a `DomainError` becomes an error message and a failure code.
     *
     * @param callable(SymfonyStyle): void $work
     */
    protected function attempt(InputInterface $input, OutputInterface $output, callable $work): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $work($io);
        } catch (DomainError $domainError) {
            $io->error($domainError->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function addDataOptions(): void
    {
        $this
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'A YAML or JSON file holding the values, by the keys of the normalized form')
            ->addOption('field', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A string value, "key=value"; "templateData.title=…" sets a key inside a map')
            ->addOption('json', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A typed value, "key=<json>": "seoNoIndex=true", "slug=null"');
    }

    /**
     * @return array<string, mixed>
     */
    protected function data(InputInterface $input): array
    {
        $file = $input->getOption('file');

        return SubmittedFields::read(
            \is_string($file) ? $file : null,
            $this->strings($input->getOption('field')),
            $this->strings($input->getOption('json')),
        );
    }

    protected function argument(InputInterface $input, string $name): string
    {
        $value = $input->getArgument($name);

        return \is_string($value) ? $value : '';
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $values): array
    {
        return \is_array($values) ? array_values(array_filter($values, \is_string(...))) : [];
    }
}
