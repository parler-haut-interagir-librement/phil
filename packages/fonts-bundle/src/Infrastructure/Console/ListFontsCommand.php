<?php

declare(strict_types=1);

namespace Phil\FontsBundle\Infrastructure\Console;

use Override;
use Phil\Fonts\Format;
use Phil\FontsBundle\Application\CatalogueEntry;
use Phil\FontsBundle\Application\FontCatalogue;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 *     bin/console phil:fonts:list.
 *
 * The font files of the directory, one line per face: its format, family, style, weight, embedding permission and the
 * SPDX identifier of its licence; a file that cannot be read shows why. It fails when a file cannot be read for another
 * reason than a missing `ext-brotli`.
 */
#[AsCommand(name: 'phil:fonts:list', description: 'Lists the font files of the directory, with their faces, embedding and licence')]
final class ListFontsCommand extends Command
{
    public function __construct(
        private readonly FontCatalogue $catalogue,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $entries = $this->catalogue->entries();

        if ([] === $entries) {
            $io->writeln(\sprintf('There is no font file in "%s".', $this->catalogue->directory()));

            return Command::SUCCESS;
        }

        $rows = [];
        $failed = false;

        foreach ($entries as $entry) {
            if (null === $entry->file) {
                $woff2 = Format::Woff2 === $entry->format && !$this->catalogue->files()->readsWoff2();
                $failed = $failed || !$woff2;
                $rows[] = [$entry->path, $entry->format->value, $woff2 ? 'Not read: ext-brotli is not loaded' : $entry->error, '', '', '', ''];

                continue;
            }

            foreach ($entry->file->faces as $font) {
                $rows[] = [$entry->path, $entry->format->value, $font->family(), $font->style->value, $font->weight, $font->embedding->usage->value, $font->licence()->spdx() ?? '?'];
            }
        }

        $io->table(['File', 'Format', 'Family', 'Style', 'Weight', 'Embedding', 'Licence'], $rows);

        if (!$this->catalogue->files()->readsWoff2() && [] !== array_filter($entries, static fn (CatalogueEntry $entry): bool => Format::Woff2 === $entry->format)) {
            $io->writeln('WOFF2 files are read when ext-brotli is loaded; without it, their WOFF, TrueType or OpenType files are read instead.');
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
