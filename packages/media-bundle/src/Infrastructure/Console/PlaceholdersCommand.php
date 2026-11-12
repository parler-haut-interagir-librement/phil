<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\Console;

use Generator;
use LimitIterator;
use Override;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\Repository\MediaRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 *     bin/console phil:media:placeholders [--limit=500] [--dry-run].
 *
 * Gives a tiny preview to every raster image of known size that has none yet — the media kept before the placeholders
 * were turned on —, collection by collection, a page at a time; a medium that cannot be rendered is counted and left
 * without one. With `--limit`, it stops after that many previews; with `--dry-run`, it only counts them. Run again, it
 * renders only what is still missing.
 */
#[AsCommand(name: self::NAME, description: 'Gives a tiny preview to the images kept without one')]
final class PlaceholdersCommand extends Command
{
    public const string NAME = 'phil:media:placeholders';

    /** The media read at a time, by default. */
    public const int PAGE = 100;

    /**
     * @param int<1, max> $pageSize the media read at a time
     */
    public function __construct(
        private readonly MediaRepository $media,
        private readonly MediaLibrary $library,
        private readonly int $pageSize = self::PAGE,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'The most previews rendered in this run')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Counts the images without a preview, rendering none');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->library->hasPlaceholders()) {
            $io->error('The placeholders are off: turn on phil_media.placeholder first.');

            return Command::FAILURE;
        }

        $limit = $this->limit($input->getOption('limit'));

        if (false === $limit) {
            $io->error('The limit is a whole number of 1 or more.');

            return Command::INVALID;
        }

        $dryRun = true === $input->getOption('dry-run');
        $rendered = 0;
        $failed = 0;

        foreach (new LimitIterator($this->missing(), 0, $limit ?? -1) as $media) {
            if ($dryRun || null !== $this->library->placeholder($media)) {
                ++$rendered;
            } else {
                ++$failed;
            }
        }

        $io->success(\sprintf($dryRun ? '%d image(s) without a preview.' : '%d preview(s) rendered, %d image(s) that cannot be rendered.', $rendered, $failed));

        return Command::SUCCESS;
    }

    /**
     * The media that could have a preview and have none, collection by collection, a page at a time.
     *
     * @return Generator<int, Media>
     */
    private function missing(): Generator
    {
        foreach ([null, ...$this->media->collections()] as $collection) {
            $offset = 0;

            do {
                $page = $this->media->inCollection($collection, $this->pageSize, $offset);
                $offset += $this->pageSize;

                foreach ($page as $media) {
                    $file = $media->file();

                    if (null === $media->placeholder() && null !== $file->width && \in_array($file->mimeType, MediaLibrary::RASTER_TYPES, true)) {
                        yield $media;
                    }
                }
            } while ($this->pageSize === \count($page));
        }
    }

    private function limit(mixed $option): int|false|null
    {
        if (null === $option) {
            return null;
        }

        return \is_string($option) && 1 === preg_match('/^[1-9]\d{0,8}$/D', $option) ? (int) $option : false;
    }
}
