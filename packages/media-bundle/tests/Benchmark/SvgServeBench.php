<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Benchmark;

use DateTimeImmutable;
use Generator;
use LogicException;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Infrastructure\Doctrine\DoctrineMediaRepository;
use Phil\MediaBundle\Infrastructure\Image\GdImageProcessor;
use Phil\MediaBundle\Infrastructure\Storage\FilesystemStorage;
use Phil\MediaBundle\Infrastructure\Svg\SanitizingSvgPurifier;
use Phil\MediaBundle\Tests\Unit\Fixtures\Database;
use Phil\MediaBundle\Tests\Unit\Fixtures\Images;
use Phil\MediaBundle\Tests\Unit\Fixtures\MutableClock;

/**
 * An SVG image is purified again the first time `MediaController` serves it, then read back from the storage: the cost
 * of that first response, for a logo of a few elements and for an illustration of a few thousand paths, against the
 * responses after it.
 *
 * @BeforeMethods("setUp")
 *
 * @Groups({"media"})
 */
final class SvgServeBench
{
    private readonly MediaLibrary $library;

    private readonly SanitizingSvgPurifier $purifier;

    private ?Media $media = null;

    private string $svg = '';

    public function __construct()
    {
        $this->purifier = new SanitizingSvgPurifier();
        $this->library = new MediaLibrary(new DoctrineMediaRepository(Database::entityManager()), new FilesystemStorage(Images::directory()), new GdImageProcessor(), new MutableClock(new DateTimeImmutable('2026-11-12 12:00 UTC')), svg: $this->purifier);
    }

    /**
     * @param array{paths: int} $params
     */
    public function setUp(array $params): void
    {
        $paths = '';

        for ($i = 0; $i < $params['paths']; ++$i) {
            $paths .= \sprintf('<path d="M%1$d.123456 %1$d.654321L%2$d.5 %2$d.25Z" fill="#%3$06x"/>', $i, $i + 10, $i * 997 % 0xFFFFFF);
        }

        $this->svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'.$paths.'</svg>';
        $this->media = $this->library->upload(Images::file($this->svg), 'image.svg');
        $this->library->purifiedSvg($this->media);
    }

    /**
     * The first response: the image purified.
     *
     * @ParamProviders("images")
     */
    public function benchFirstResponse(): void
    {
        $this->purifier->purify($this->svg);
    }

    /**
     * The responses after it: the purified image read back.
     *
     * @ParamProviders("images")
     */
    public function benchLaterResponses(): void
    {
        $this->library->purifiedSvg($this->media ?? throw new LogicException('setUp() uploads the image.'));
    }

    /**
     * @return Generator<string, array{paths: int}>
     */
    public function images(): Generator
    {
        yield 'logo, 5 paths' => ['paths' => 5];
        yield 'illustration, 2,000 paths' => ['paths' => 2000];
    }
}
