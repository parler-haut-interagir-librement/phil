<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\MediaBundle\Application\ImageFormat;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Application\Transform\Crop;
use Phil\MediaBundle\Application\UrlSigner;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\ValueObject\FileInfo;
use Phil\MediaBundle\Domain\ValueObject\MediaId;
use Phil\MediaBundle\Infrastructure\Doctrine\DoctrineMediaRepository;
use Phil\MediaBundle\Infrastructure\Image\GdImageProcessor;
use Phil\MediaBundle\Infrastructure\Storage\FilesystemStorage;
use Phil\MediaBundle\Infrastructure\Twig\Component\Img;
use Phil\MediaBundle\Infrastructure\Twig\Component\Picture;
use Phil\MediaBundle\Infrastructure\Twig\ImageCandidates;
use Phil\MediaBundle\Infrastructure\Twig\MediaExtension;
use Phil\MediaBundle\Infrastructure\Twig\ResponsiveImages;
use Phil\MediaBundle\Tests\Unit\Fixtures\Database;
use Phil\MediaBundle\Tests\Unit\Fixtures\Images;
use Phil\MediaBundle\Tests\Unit\Fixtures\MutableClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\RequestContext;

#[CoversClass(ResponsiveImages::class)]
#[CoversClass(ImageCandidates::class)]
#[CoversClass(Img::class)]
#[CoversClass(Picture::class)]
final class ResponsiveImagesTest extends TestCase
{
    private MediaLibrary $library;

    private UrlSigner $signer;

    private ResponsiveImages $images;

    protected function setUp(): void
    {
        $repository = new DoctrineMediaRepository(Database::entityManager());
        $clock = new MutableClock(new DateTimeImmutable('2026-11-06 12:00 UTC'));
        $this->library = new MediaLibrary($repository, new FilesystemStorage(Images::directory()), new GdImageProcessor(), $clock, [
            'card' => ImageFormat::transforming('card', [Crop::of(300, 200)], widths: [100, 150, 300, 600]),
            'plain' => ImageFormat::of('plain', 100, 100),
        ], ImageFormat::original(widths: [200]));
        $routes = new PhpFileLoader(new FileLocator(\dirname(__DIR__, 3).'/config'))->load('routes.php');
        $routes->addPrefix('/media');

        $this->signer = new UrlSigner('secret');
        $this->images = new ResponsiveImages($this->library, new MediaExtension(new UrlGenerator($routes, new RequestContext()), $this->signer, $clock), $repository);
    }

    public function testAFormatHasItsSizeAndItsNarrowerWidths(): void
    {
        $media = $this->library->upload(Images::file(Images::png(400, 300)), 'a.png');
        $card = $this->images->candidates($media, 'card', 'avif');

        self::assertSame([$this->url($media->id, 'card.avif'), 300, 200, 'image/avif'], [$card->src, $card->width, $card->height, $card->mimeType]);
        self::assertSame(\sprintf('%s 100w, %s 150w, %s 300w', $this->url($media->id, 'card@100.avif'), $this->url($media->id, 'card@150.avif'), $this->url($media->id, 'card.avif')), $card->srcset);

        $plain = $this->images->candidates($media, 'plain', 'jpeg');
        self::assertSame([$this->url($media->id, 'plain.jpeg'), null, 100, 100, 'image/jpeg'], [$plain->src, $plain->srcset, $plain->width, $plain->height, $plain->mimeType]);

        $original = $this->images->candidates($media, 'original', 'webp');
        self::assertSame([400, 300, \sprintf('%s 200w, %s 400w', $this->url($media->id, 'original@200.webp'), $this->url($media->id, 'original.webp'))], [$original->width, $original->height, $original->srcset]);

        $this->expectException(InvalidMedia::class);
        $this->expectExceptionMessageIsOrContains('An image is rendered as webp, avif, jpeg, png; not as "gif".');
        $this->images->candidates($media, 'plain', 'gif');
    }

    public function testTheFocalPointMovesTheSize(): void
    {
        $media = $this->library->upload(Images::file(Images::png(200, 100)), 'a.png');
        $media->focus(0.0, 0.0);

        self::assertSame([200, 100], [$this->images->candidates($media, 'card', 'webp')->width, $this->images->candidates($media, 'card', 'webp')->height]);
        self::assertSame(\sprintf('%s 100w, %s 150w, %s 200w', $this->url($media->id, 'card@100.webp'), $this->url($media->id, 'card@150.webp'), $this->url($media->id, 'card.webp')), $this->images->candidates($media, 'card', 'webp')->srcset);
    }

    public function testAMediumThatCannotBeRenderedIsItsFile(): void
    {
        $pdf = $this->library->upload(Images::file('%PDF-1.4'), 'a.pdf');
        $svg = $this->library->upload(Images::file('<svg xmlns="http://www.w3.org/2000/svg" width="4" height="4"/>'), 'a.svg');
        $jpeg = $this->library->upload(Images::file(Images::jpeg(8, 4)), 'a.jpg');
        $gif = $this->library->upload(Images::file(Images::gif(8, 4)), 'a.gif');

        foreach ([$pdf, $svg] as $media) {
            $candidates = $this->images->candidates($media, 'card', 'webp');
            self::assertFalse($this->images->renderable($media));
            self::assertSame([$this->url($media->id, 'original'), null, null, null, $media->file()->mimeType], [$candidates->src, $candidates->srcset, $candidates->width, $candidates->height, $candidates->mimeType]);
        }

        foreach ([FileInfo::of('a.png', 'image/png', 10, 4), FileInfo::of('a.png', 'image/png', 10, null, 4)] as $file) {
            $partial = Media::upload($file, null, new DateTimeImmutable('2026-11-06 12:00 UTC'));
            self::assertFalse($this->images->renderable($partial));
            self::assertSame([null, null], [$this->images->candidates($partial, 'card', 'webp')->width, $this->images->candidates($partial, 'card', 'webp')->srcset]);
        }

        self::assertSame([true, true, 'jpeg', 'png', 'png'], [$this->images->renderable($jpeg), $this->images->renderable($gif), $this->images->fallbackType($jpeg), $this->images->fallbackType($gif), $this->images->fallbackType($this->library->upload(Images::file(Images::png(4, 4)), 'a.png'))]);
        self::assertSame(['png', 'png'], [$this->images->fallbackType($this->library->upload(Images::file(Images::webp(4, 4)), 'a.webp')), $this->images->fallbackType($this->library->upload(Images::file(Images::avif(4, 4)), 'a.avif'))]);
    }

    public function testAMediumIsFoundByItselfOrItsIdentifier(): void
    {
        $media = $this->library->upload(Images::file(Images::png(4, 4)), 'a.png');

        self::assertSame([$media, $media, $media, null, null, null], [
            $this->images->resolve($media),
            $this->images->resolve(MediaId::fromString($media->id)),
            $this->images->resolve($media->id),
            $this->images->resolve(null),
            $this->images->resolve('not-a-medium'),
            $this->images->resolve('019a5c3e-1f2b-7c4d-8e9f-0a1b2c3d4e5f'),
        ]);
    }

    public function testTheComponentsAskTheirImages(): void
    {
        $media = $this->library->upload(Images::file(Images::png(400, 300)), 'a.png');
        $media->describe(null, 'A red and blue square');

        $img = new Img($this->images);
        $img->media = $media->id;
        $img->format = 'card';

        self::assertSame([$media, 'webp', 'lazy', 'async', null, 'A red and blue square'], [$img->medium(), $img->type, $img->loadingMode(), $img->decodingMode(), $img->sizes, $img->altText($media)]);
        self::assertSame($this->url($media->id, 'card.webp'), $img->image($media)->src);
        $img->alt = '';
        $img->type = 'png';
        self::assertSame(['', $this->url($media->id, 'card.png')], [$img->altText($media), $img->image($media)->src]);
        self::assertNull(new Img($this->images)->medium());

        $picture = new Picture($this->images);
        $picture->media = $media;
        $picture->format = 'card';
        self::assertSame([$media, ['avif', 'webp'], 'lazy', 'async', null, 'A red and blue square'], [$picture->medium(), $picture->types, $picture->loadingMode(), $picture->decodingMode(), $picture->sizes, $picture->altText($media)]);
        self::assertSame(['image/avif', 'image/webp'], array_map(static fn (ImageCandidates $source): string => $source->mimeType, $picture->sources($media)));
        self::assertSame([$this->url($media->id, 'card.png'), 'image/png'], [$picture->fallback($media)->src, $picture->fallback($media)->mimeType]);
        $picture->types = ['webp'];
        $picture->alt = 'Squares';
        self::assertSame(['Squares', [$this->url($media->id, 'card.webp')]], [$picture->altText($media), array_map(static fn (ImageCandidates $source): string => $source->src, $picture->sources($media))]);

        $pdf = $this->library->upload(Images::file('%PDF-1.4'), 'a.pdf');
        self::assertSame([[], $this->url($pdf->id, 'original'), ''], [$picture->sources($pdf), $picture->fallback($pdf)->src, new Picture($this->images)->altText($pdf)]);
        self::assertSame('', new Img($this->images)->altText($pdf));
    }

    private function url(string $id, string $file): string
    {
        $path = \sprintf('/media/%s/1/%s', $id, $file);

        return $path.'?signature='.$this->signer->signature($path, null);
    }
}
