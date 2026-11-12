<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Console;

use Override;
use Phil\ContentBundle\Application\ContentManager;
use Phil\ContentBundle\Application\Contract\ConversionLosses;
use Phil\ContentBundle\Application\Publication\ConversionLoss;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\Markup\MarkupFormat;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 *     bin/console phil:content:publish news:0192f3c4-… fr.
 *
 * Before publishing, what the conversion of the draft's body into UJO does not keep is listed; asked, the editor
 * publishes anyway or stops. Without a terminal to ask, the list is shown and the draft published.
 */
#[AsCommand(name: 'phil:content:publish', description: 'Publishes the draft of one locale of a content root')]
final class PublishContentCommand extends ContentCommand
{
    public function __construct(
        ContentManager $contents,
        private readonly ?ConversionLosses $losses = null,
    ) {
        parent::__construct($contents);
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('reference', InputArgument::REQUIRED, 'The content: "resource_key:identifier"')
            ->addArgument('locale', InputArgument::REQUIRED, 'The locale')
            ->addOption('accept-losses', null, InputOption::VALUE_NONE, 'Publish even when the conversion of the body into UJO loses part of it');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->attempt($input, $output, function (SymfonyStyle $io) use ($input): void {
            $reference = ContentReference::fromString($this->argument($input, 'reference'));
            $locale = $this->argument($input, 'locale');

            $accepted = $this->accepts($input, $io, $this->contents->draft($reference, $locale));

            if (false === $accepted) {
                $io->note(\sprintf('%s in %s is not published.', $reference->toString(), $locale));

                return;
            }

            // Without an answer — no losses, or no terminal to ask — the bundle decides: a publication that loses
            // content is refused.
            $this->contents->publish($reference, $locale, true === $accepted);

            $io->success(\sprintf('Published %s in %s.', $reference->toString(), $locale));
        });
    }

    /**
     * Whether the editor accepts the losses of the draft's body in UJO, after their list: `true` with
     * `--accept-losses` or a yes, `false` with a no, `null` when there is nothing to accept or nobody to ask.
     *
     * @param array<string, mixed> $draft
     */
    private function accepts(InputInterface $input, SymfonyStyle $io, array $draft): ?bool
    {
        $source = $draft['bodySource'] ?? null;
        $format = MarkupFormat::tryFrom(\is_string($draft['bodyFormat'] ?? null) ? $draft['bodyFormat'] : '');
        $losses = !$this->losses instanceof ConversionLosses || !\is_string($source) || null === $format ? [] : $this->losses->of($source, $format);

        if ([] === $losses) {
            return null;
        }

        $io->warning('The conversion of the body into UJO does not keep:');
        $io->table(['Element', 'Path', 'Reason'], array_map(static fn (ConversionLoss $loss): array => [$loss->element, $loss->path, $loss->reason], $losses));

        if (true === $input->getOption('accept-losses')) {
            return true;
        }

        return $input->isInteractive() ? $io->confirm('Publish anyway?', false) : null;
    }
}
