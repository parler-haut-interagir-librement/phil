<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Application;

use Phil\MediaBundle\Application\ImageFormat;
use Phil\MediaBundle\Application\UrlSigner;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImageFormat::class)]
#[CoversClass(UrlSigner::class)]
final class FormatsAndSignaturesTest extends TestCase
{
    public function testAFormat(): void
    {
        $format = ImageFormat::of('thumb_2', 320, 200, 'fit', 90);

        self::assertSame(['thumb_2', ['type' => 'thumbnail', 'width' => 320, 'height' => 200, 'mode' => 'fit'], 90, []], [$format->name, $format->transformers[0]->definition(), $format->quality, $format->widths]);
        self::assertCount(1, $format->transformers);
        self::assertSame([['type' => 'thumbnail', 'width' => 1, 'height' => 1, 'mode' => 'crop'], 80], [ImageFormat::of('a', 1, 1)->transformers[0]->definition(), ImageFormat::of('a', 1, 1)->quality]);
        self::assertSame([['type' => 'thumbnail', 'width' => 8000, 'height' => 8000, 'mode' => 'crop'], 1, 100], [ImageFormat::of('a', 8000, 8000)->transformers[0]->definition(), ImageFormat::of('a', 1, 1, 'crop', 1)->quality, ImageFormat::of('a', 1, 1, 'crop', 100)->quality]);
        self::assertSame(['crop', 'fit'], ImageFormat::MODES);
        self::assertSame('a'.str_repeat('b', 31), ImageFormat::of('a'.str_repeat('b', 31), 1, 1)->name);

        foreach ([
            [static fn (): ImageFormat => ImageFormat::of('original', 1, 1), 'A format is 1 to 32 lower-case letters, digits or "_", and not "original"; "original" is not.'],
            [static fn (): ImageFormat => ImageFormat::of('Thumb', 1, 1), 'A format is 1 to 32 lower-case letters, digits or "_", and not "original"; "Thumb" is not.'],
            [static fn (): ImageFormat => ImageFormat::of("a\n", 1, 1), "A format is 1 to 32 lower-case letters, digits or \"_\", and not \"original\"; \"a\n\" is not."],
            [static fn (): ImageFormat => ImageFormat::of('1a', 1, 1), 'A format is 1 to 32 lower-case letters, digits or "_", and not "original"; "1a" is not.'],
            [static fn (): ImageFormat => ImageFormat::of('a'.str_repeat('b', 32), 1, 1), 'A format is 1 to 32 lower-case letters, digits or "_", and not "original"; "a'.str_repeat('b', 32).'" is not.'],
            [static fn (): ImageFormat => ImageFormat::of('a', 0, 1), 'A transformation is 1 to 8000 pixels across and down; 0 is not.'],
            [static fn (): ImageFormat => ImageFormat::of('a', 1, 0), 'A transformation is 1 to 8000 pixels across and down; 0 is not.'],
            [static fn (): ImageFormat => ImageFormat::of('a', 8001, 1), 'A transformation is 1 to 8000 pixels across and down; 8001 is not.'],
            [static fn (): ImageFormat => ImageFormat::of('a', 1, 8001), 'A transformation is 1 to 8000 pixels across and down; 8001 is not.'],
            [static fn (): ImageFormat => ImageFormat::of('a', 1, 1, 'stretch'), 'A thumbnail crops or fits; "stretch" is neither.'],
            [static fn (): ImageFormat => ImageFormat::of('a', 1, 1, 'crop', 0), 'A quality is from 1 to 100.'],
            [static fn (): ImageFormat => ImageFormat::of('a', 1, 1, 'crop', 101), 'A quality is from 1 to 100.'],
        ] as [$make, $message]) {
            try {
                $make();
                self::fail($message);
            } catch (InvalidMedia $invalidMedia) {
                self::assertSame($message, $invalidMedia->getMessage());
            }
        }
    }

    public function testAUrlIsSigned(): void
    {
        $signer = new UrlSigner('secret');
        $forever = $signer->signature('/media/a/1/original', null);
        $until = $signer->signature('/media/a/1/original', 2000);

        self::assertSame(43, \strlen($forever));
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $forever);
        self::assertSame(rtrim(strtr(base64_encode(hash_hmac('sha256', '/media/a/1/original|', 'secret', true)), '+/', '-_'), '='), $forever);
        self::assertSame('Mru_y0wAdm3CiJm8YH4UXVSBaT6WZlbhQvPc_B-D5jI', $until);
        self::assertNotSame($forever, new UrlSigner('other')->signature('/media/a/1/original', null));
        self::assertTrue($signer->verify('/media/a/1/original', null, $forever, 99_999));
        self::assertTrue($signer->verify('/media/a/1/original', 2000, $until, 2000));
        self::assertFalse($signer->verify('/media/a/1/original', 2000, $until, 2001));
        self::assertFalse($signer->verify('/media/a/2/original', null, $forever, 0));
        self::assertFalse($signer->verify('/media/a/1/original', 2000, $forever, 0));
    }
}
