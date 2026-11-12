<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\MediaBundle\Application\ImageFormat;
use Phil\MediaBundle\Application\ImageProcessor;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\Model\MediaCollection;
use Phil\MediaBundle\Infrastructure\Console\PlaceholdersCommand;
use Phil\MediaBundle\Infrastructure\Doctrine\DoctrineMediaRepository;
use Phil\MediaBundle\Infrastructure\Image\GdImageProcessor;
use Phil\MediaBundle\Infrastructure\Storage\FilesystemStorage;
use Phil\MediaBundle\Tests\Unit\Fixtures\Database;
use Phil\MediaBundle\Tests\Unit\Fixtures\Images;
use Phil\MediaBundle\Tests\Unit\Fixtures\MutableClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(PlaceholdersCommand::class)]
final class PlaceholdersCommandTest extends TestCase
{
    private DoctrineMediaRepository $repository;

    private FilesystemStorage $storage;

    private MutableClock $clock;

    protected function setUp(): void
    {
        $this->repository = new DoctrineMediaRepository(Database::entityManager());
        $this->storage = new FilesystemStorage(Images::directory());
        $this->clock = new MutableClock(new DateTimeImmutable('2026-11-12 12:00 UTC'));
    }

    public function testTheImagesKeptWithoutAPreviewGetOne(): void
    {
        $events = MediaCollection::create('events', 'Events');
        $empty = MediaCollection::create('empty', 'Empty');
        $this->repository->save($events, $empty);
        $before = new MediaLibrary($this->repository, $this->storage, new GdImageProcessor(), $this->clock);
        $loose = [];

        foreach (range(1, 3) as $index) {
            $loose[] = $before->upload(Images::file(Images::png(10 + $index, 10)), $index.'.png');
            $this->clock->now = $this->clock->now->modify('+1 second');
        }

        $grouped = $before->upload(Images::file(Images::jpeg(20, 10)), 'party.jpg', $events);
        $pdf = $before->upload(Images::file('%PDF-1.4'), 'a.pdf');
        $svg = $before->upload(Images::file('<svg xmlns="http://www.w3.org/2000/svg"/>'), 'a.svg');
        // A PNG whose size is unknown, and a bitmap no processor reads: neither can have a preview.
        $broken = $before->upload(Images::file("\x89PNG\r\n\x1a\n broken"), 'broken.png');
        ob_start();
        imagebmp(Images::canvas(4, 4));
        $bitmap = $before->upload(Images::file((string) ob_get_clean()), 'a.bmp');
        self::assertSame([null, 4], [$broken->file()->width, $bitmap->file()->width]);

        $library = $this->library(new GdImageProcessor());
        // Already given one: left as it is.
        $loose[0]->preview('data:image/webp;base64,UklGRg==');
        $this->repository->save($loose[0]);

        $dry = $this->execute($library, ['--dry-run' => true], 2);
        self::assertSame(Command::SUCCESS, $dry->getStatusCode());
        self::assertStringContainsString(' 3 image(s) without a preview.', $dry->getDisplay());
        self::assertNull($grouped->placeholder());

        $run = $this->execute($library, [], 2);
        self::assertSame(Command::SUCCESS, $run->getStatusCode());
        self::assertStringContainsString(' 3 preview(s) rendered, 0 image(s) that cannot be rendered.', $run->getDisplay());

        foreach ([$loose[1], $loose[2], $grouped] as $media) {
            self::assertStringStartsWith('data:image/webp;base64,', (string) $this->reloaded($media)->placeholder());
        }

        self::assertSame('data:image/webp;base64,UklGRg==', $this->reloaded($loose[0])->placeholder());
        self::assertSame([null, null], [$this->reloaded($pdf)->placeholder(), $this->reloaded($svg)->placeholder()]);
        self::assertStringContainsString('0 preview(s) rendered', $this->execute($library, [], 3)->getDisplay());
    }

    public function testTheLimitStopsTheRunAndAFailureIsCounted(): void
    {
        $before = new MediaLibrary($this->repository, $this->storage, new GdImageProcessor(), $this->clock);

        foreach (range(1, 4) as $index) {
            $before->upload(Images::file(Images::png(10, 10 + $index)), $index.'.png');
            $this->clock->now = $this->clock->now->modify('+1 second');
        }

        $failing = new class implements ImageProcessor {
            public int $calls = 0;

            public function render(string $image, ImageFormat $format, array $focalPoint, string $type): string
            {
                ++$this->calls;

                throw InvalidMedia::because('No.');
            }
        };

        $limited = $this->execute($this->library($failing), ['--limit' => '3'], 100);
        self::assertStringContainsString('0 preview(s) rendered, 3 image(s) that cannot be rendered.', $limited->getDisplay());
        self::assertSame(3, $failing->calls);
        self::assertStringContainsString(' 1 image(s) without a preview.', $this->execute($this->library(new GdImageProcessor()), ['--limit' => '1', '--dry-run' => true], 100)->getDisplay());
        self::assertStringContainsString(' 4 preview(s) rendered', $this->execute($this->library(new GdImageProcessor()), ['--limit' => '4'], 1)->getDisplay());
    }

    public function testThePlaceholdersMustBeOnAndTheLimitAWholeNumber(): void
    {
        $off = $this->execute(new MediaLibrary($this->repository, $this->storage, new GdImageProcessor(), $this->clock), [], 100);
        self::assertSame(Command::FAILURE, $off->getStatusCode());
        self::assertStringContainsString('The placeholders are off: turn on phil_media.placeholder first.', $off->getDisplay());

        foreach (['0', '-1', 'ten', '1.5', '1x', 'x1', "3\n", '1234567890', ['3']] as $limit) {
            $invalid = $this->execute($this->library(new GdImageProcessor()), ['--limit' => $limit], 100);
            self::assertSame(Command::INVALID, $invalid->getStatusCode(), json_encode($limit, \JSON_THROW_ON_ERROR));
            self::assertStringContainsString('The limit is a whole number of 1 or more.', $invalid->getDisplay());
        }

        self::assertStringContainsString('0 image(s) without a preview.', $this->execute($this->library(new GdImageProcessor()), ['--limit' => '123456789', '--dry-run' => true], 100)->getDisplay());
        self::assertSame(PlaceholdersCommand::NAME, new PlaceholdersCommand($this->repository, $this->library(new GdImageProcessor()))->getName());
        self::assertSame('phil:media:placeholders', PlaceholdersCommand::NAME);
        self::assertSame(100, PlaceholdersCommand::PAGE);
    }

    /**
     * @param array<string, mixed> $input
     * @param int<1, max>          $pageSize
     */
    private function execute(MediaLibrary $library, array $input, int $pageSize): CommandTester
    {
        $tester = new CommandTester(new PlaceholdersCommand($this->repository, $library, $pageSize));
        $tester->execute($input);

        return $tester;
    }

    private function library(ImageProcessor $processor): MediaLibrary
    {
        return new MediaLibrary($this->repository, $this->storage, $processor, $this->clock, placeholder: ImageFormat::of('placeholder', 16, 16, 'fit', 40));
    }

    private function reloaded(Media $media): Media
    {
        $found = $this->repository->get($media->id);
        self::assertInstanceOf(Media::class, $found);

        return $found;
    }
}
