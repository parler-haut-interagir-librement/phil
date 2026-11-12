<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Unit;

use Phil\UxKit\Application\ImageTextCheck;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImageTextCheck::class)]
final class ImageTextCheckTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function described(): iterable
    {
        yield 'what the image shows' => ['/images/bridge.jpg', 'A bridge at dusk'];
        yield 'a decorative image' => ['/images/bridge.jpg', ''];
        yield 'an inline image' => ['data:image/png;base64,AAAA', 'A teal square'];
        yield 'a word that starts like a generic one' => ['/images/a.jpg', 'Imagery of the coast'];
        yield 'a photo that is the subject' => ['/images/a.jpg', 'Photographers at work'];
        yield 'a name that holds the file name' => ['/images/bridge.jpg', 'The bridge'];
        yield 'an address of another host' => ['https://images.example.com/stage.jpg', 'The stage at night'];
        yield 'an address without a path' => ['https://images.example.com', 'The stage'];
        yield 'a query alone' => ['?v=2', 'The stage'];
        yield 'an address phil/url refuses' => ['https://user@images.example.com/stage.jpg', 'stage'];
        yield 'a path holding a space' => ['/images/the stage.jpg', 'the stage'];
        yield 'an address of credentials in capitals' => ['HTTPS://user@images.example.com/stage.jpg', 'stage'];
        yield 'an inline image named as a word' => ['data:image/png;base64,stage', 'stage'];
        yield 'an address of an IP' => ['https://192.168.0.1/stage.jpg', 'stage'];
    }

    #[DataProvider('described')]
    public function testATextThatSaysWhatTheImageShowsPasses(string $src, string $alt): void
    {
        self::assertSame([], new ImageTextCheck()->problems($src, $alt, 800, 600));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function fileNames(): iterable
    {
        yield 'the name without its extension' => ['/images/bridge.jpg', 'bridge'];
        yield 'the name with its extension' => ['/images/bridge.jpg', 'bridge.jpg'];
        yield 'in another case' => ['/images/Bridge.JPG', 'BRIDGE.jpg'];
        yield 'its dashes and underscores read as spaces' => ['/images/old_stone-bridge.jpg', 'Old stone bridge'];
        yield 'a relative path' => ['images/bridge.jpg', 'bridge'];
        yield 'a path that climbs' => ['../bridge.jpg', 'bridge'];
        yield 'a density suffix' => ['/images/logo@2x.png', 'logo@2x'];
        yield 'the name before its density' => ['/images/studio@2x.png', 'studio'];
        yield 'the name before its width' => ['/images/hall@480w.avif', 'hall'];
        yield 'the name before the digest of AssetMapper' => ['/assets/images/hall-3fA9c1d.jpg', 'hall'];
        yield 'the versioned name itself' => ['/assets/images/hall-3fA9c1d.jpg', 'hall-3fA9c1d'];
        yield 'escaped' => ['/images/salle%20principale.jpg', 'salle principale'];
        yield 'after a query' => ['/images/hall.jpg?v=2', 'hall'];
        yield 'of another host' => ['https://images.example.com/photos/stage.jpg', 'stage'];
        yield 'of another host without a scheme' => ['//images.example.com/stage.jpg', 'stage'];
        yield 'with a trailing period' => ['/images/stage.jpg', 'Stage.'];
        yield 'with line breaks in the address' => ["\n/images/\tstage.jpg ", 'stage'];
        yield 'of localhost' => ['http://localhost:8000/stage.jpg', 'stage'];
        yield 'a path holding a colon' => ['/images/a:b/stage.jpg', 'stage'];
        yield 'dashes around the name' => ['/images/-bridge-.jpg', 'bridge'];
        yield 'capitals outside ASCII' => ['/images/ÉTÉ.jpg', 'été'];
        yield 'the digest after a density' => ['/assets/images/studio@2x-3fA9c1d.png', 'studio'];
        yield 'the density before the digest' => ['/assets/images/studio@2x-3fA9c1d.png', 'studio@2x'];
    }

    #[DataProvider('fileNames')]
    public function testTheNameOfTheFileIsNoTextAlternative(string $src, string $alt): void
    {
        self::assertSame([ImageTextCheck::FILE_NAME], array_keys(new ImageTextCheck()->problems($src, $alt, 800, 600)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function generic(): iterable
    {
        yield 'image' => ['image'];
        yield 'Image, capitalised, with a period' => ['Image.'];
        yield 'photo' => ['photo'];
        yield 'picture' => ['Picture'];
        yield 'img' => ['img'];
        yield 'logo' => ['Logo'];
        yield 'icon' => ['icon'];
        yield 'graphic' => ['graphic'];
        yield 'illustration' => ['Illustration'];
        yield 'banner' => ['banner'];
        yield 'thumbnail' => ['thumbnail'];
        yield 'screenshot' => ['Screenshot'];
        yield 'untitled' => ['Untitled'];
        yield 'placeholder' => ['placeholder'];
        yield 'photographie' => ['Photographie'];
        yield 'icône' => ['Icône'];
        yield 'bannière' => ['Bannière'];
        yield 'vignette' => ['vignette'];
        yield 'sans titre' => ['Sans titre'];
        yield 'spaces around' => ['  image  '];
        yield 'capitals outside ASCII' => ['ICÔNE'];
    }

    #[DataProvider('generic')]
    public function testAGenericWordSaysNothing(string $alt): void
    {
        self::assertSame([ImageTextCheck::GENERIC], array_keys(new ImageTextCheck()->problems('/a.jpg', $alt, 1, 1)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function redundant(): iterable
    {
        yield 'photo of' => ['Photo of a bridge'];
        yield 'a photo of' => ['A photo of a bridge'];
        yield 'an image of' => ['An image of the stage'];
        yield 'the picture of' => ['The picture of the crowd'];
        yield 'image showing' => ['Image showing the map'];
        yield 'screenshot of' => ['Screenshot of the settings'];
        yield 'photo de' => ['Photo de la salle'];
        yield 'une photo de' => ['Une photo de la salle'];
        yield "photo d'" => ["Photo d'un pont"];
        yield 'photo d’ typographic' => ['Photo d’un pont'];
        yield 'image du' => ['Image du festival'];
        yield 'images des' => ['Images des bénévoles'];
        yield "l'image de" => ["L'image de la scène"];
        yield 'capture d’écran de' => ['Capture d’écran de la page'];
        yield 'illustration of' => ['Illustration of the plan'];
    }

    #[DataProvider('redundant')]
    public function testSayingItIsAnImageSaysWhatScreenReadersAlreadySay(string $alt): void
    {
        self::assertSame([ImageTextCheck::REDUNDANT], array_keys(new ImageTextCheck()->problems('/a.jpg', $alt, 1, 1)));
    }

    /**
     * @return iterable<string, array{mixed, mixed}>
     */
    public static function sizes(): iterable
    {
        yield 'no width' => [null, 600];
        yield 'no height' => [800, null];
        yield 'neither' => [null, null];
        yield 'an empty width' => ['', 600];
        yield 'a width of zero' => [0, 600];
        yield 'a negative height' => [800, -1];
        yield 'a height that is no number' => [800, 'auto'];
        yield 'a width with a unit' => ['800px', 600];
        yield 'a width that is a float' => [800.5, 600];
        yield 'a width after a letter' => ['v1', 600];
        yield 'a height before a line break' => [800, "600\n"];
    }

    #[DataProvider('sizes')]
    public function testAnImageWithoutItsSizeShiftsThePage(mixed $width, mixed $height): void
    {
        self::assertSame([ImageTextCheck::NO_SIZE], array_keys(new ImageTextCheck()->problems('/a.jpg', 'A bridge', $width, $height)));
    }

    public function testASizeWrittenAsANumberInTextIsASize(): void
    {
        self::assertSame([], new ImageTextCheck()->problems('/a.jpg', 'A bridge', '800', '600'));
    }

    public function testADecorativeImageStillNeedsItsSize(): void
    {
        self::assertSame([ImageTextCheck::NO_SIZE], array_keys(new ImageTextCheck()->problems('/image.jpg', '', null, 1)));
    }

    public function testEachProblemIsSaidWithTheImageAndItsText(): void
    {
        self::assertSame(
            [
                ImageTextCheck::FILE_NAME => 'The text alternative of the image "/images/logo.png" is "logo", the name of its file: say what the image shows, or leave it empty if it is decorative.',
                ImageTextCheck::GENERIC => 'The text alternative of the image "/images/logo.png" is "logo", a word that says only that it is an image: say what it shows, or leave it empty if it is decorative.',
                ImageTextCheck::NO_SIZE => 'The image "/images/logo.png" has no width or no height: give both, so that its space is reserved before it loads and the page does not shift.',
            ],
            new ImageTextCheck()->problems('/images/logo.png', 'logo', null, 32),
        );
        self::assertSame(
            [ImageTextCheck::REDUNDANT => 'The text alternative of the image "/a.jpg" is "Photo of a bridge": screen readers already say it is an image, start with what it shows.'],
            new ImageTextCheck()->problems('/a.jpg', 'Photo of a bridge', 1, 1),
        );
    }
}
