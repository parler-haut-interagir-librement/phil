<?php

declare(strict_types=1);

/*
 * The page `composer bench:worker phil/print-compositor` measures: FrankenPHP keeps this script alive across requests,
 * so the compositor and the type face read here serve every request, as one service of an application's worker does.
 * Each request composes a zone of 100 × 100 mm at 150 dpi, an image under a fitted text, and prints its PNG file. It
 * needs the GD extension in the FrankenPHP image.
 */

use Phil\PrintCompositor\Box;
use Phil\PrintCompositor\Colour;
use Phil\PrintCompositor\ImageItem;
use Phil\PrintCompositor\Layer;
use Phil\PrintCompositor\PrintCompositor;
use Phil\PrintCompositor\PrintJob;
use Phil\PrintCompositor\Raster;
use Phil\PrintCompositor\Tests\Unit\Fixture\Fonts;
use Phil\PrintCompositor\TextItem;
use Phil\PrintCompositor\TypeFace;
use Phil\PrintCompositor\Zone;

// The package's own vendor/ once split, the monorepo's while it is developed there.
$autoload = dirname(__DIR__, 2).'/vendor/autoload.php';
require is_file($autoload) ? $autoload : dirname(__DIR__, 4).'/vendor/autoload.php';

$compositor = new PrintCompositor();
$face = TypeFace::fromFile(Fonts::trueType());
$artwork = Raster::blank(400, 400, Colour::fromHex('#ff8000'));

$page = static function () use ($compositor, $face, $artwork): void {
    $job = new PrintJob(new Zone('front', 100, 100), [
        new Layer('artwork', [new ImageItem($artwork, new Box(0, 0, 100, 60))]),
        new Layer('text', [new TextItem('Rest in pieces, the quick brown fox.', $face, new Box(10, 60, 80, 30), Colour::fromHex('#1a1a1a'), maxSize: 48, minSize: 6)]),
    ], 150);

    header('Content-Type: image/png');
    echo $compositor->compose($job)->png();
};

while (frankenphp_handle_request($page)) {
    gc_collect_cycles();
}
