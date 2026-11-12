<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Console;

use Override;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\ContentBundle\Infrastructure\Http\PreviewLinks;
use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;
use Phil\LocaleDimensions\Locale;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 *     bin/console phil:content:preview-link news:0192f3c4-… fr.
 *
 * Prints the signed link to the draft of a content in one locale, for whoever should read it before it is published.
 */
#[AsCommand(name: 'phil:content:preview-link', description: 'Prints a signed link to the draft of one locale of a content root')]
final class PreviewLinkCommand extends Command
{
    public function __construct(
        private readonly PreviewLinks $links,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('reference', InputArgument::REQUIRED, 'The content: "resource_key:identifier"')
            ->addArgument('locale', InputArgument::REQUIRED, 'The locale');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $output->writeln($this->links->url(ContentReference::fromString($this->argument($input, 'reference')), Locale::fromString($this->argument($input, 'locale'))));
        } catch (InvalidContent|InvalidLocaleDimension $invalid) {
            new SymfonyStyle($input, $output)->error($invalid->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function argument(InputInterface $input, string $name): string
    {
        $value = $input->getArgument($name);

        return \is_string($value) ? $value : '';
    }
}
