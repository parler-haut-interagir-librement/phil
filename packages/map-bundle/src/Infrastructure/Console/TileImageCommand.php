<?php

declare(strict_types=1);

namespace Phil\MapBundle\Infrastructure\Console;

use Phil\MapBundle\Application\Contract\ImageTiler;
use Phil\MapBundle\Domain\Exception\TilingFailed;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * `phil:map:tile-image`: cuts a large image into the tiles of a floor plan, and says the size and the native zoom the
 * plan is then given.
 */
#[AsCommand(name: 'phil:map:tile-image', description: 'Cuts a large image into the tiles of a floor plan.')]
final readonly class TileImageCommand
{
    public function __construct(
        private ImageTiler $tiler,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The image: PNG, JPEG, GIF or WebP.')]
        string $image,
        #[Argument('The directory the tiles are written to, as <z>/<x>/<y>.png.')]
        string $directory,
    ): int {
        try {
            $pyramid = $this->tiler->tile($image, $directory);
        } catch (TilingFailed $tilingFailed) {
            $io->error($tilingFailed->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('%d tiles of %d pixels written: the plan is %d × %d pixels, at its own size at zoom %d.', $pyramid->tiles, $pyramid->tileSize, $pyramid->width, $pyramid->height, $pyramid->nativeZoom));

        return Command::SUCCESS;
    }
}
