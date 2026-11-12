<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use DateTimeZone;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MediaBundle\Domain\Exception\MediaException;
use Phil\MediaBundle\Domain\Media\AiDisclosure;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\Model\MediaCollection;
use Phil\MediaBundle\Domain\Model\MediaVersion;
use Phil\MediaBundle\Domain\ValueObject\FileInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Media::class)]
#[CoversClass(MediaVersion::class)]
#[CoversClass(MediaCollection::class)]
#[CoversClass(FileInfo::class)]
#[CoversClass(InvalidMedia::class)]
final class ModelTest extends TestCase
{
    public function testAFileIsDescribed(): void
    {
        $file = FileInfo::of(' Été 2026 (final).PNG ', 'image/png', 1024, 400, 200);

        self::assertSame(['Été 2026 (final).PNG', 'image/png', 1024, 400, 200, true, 't-2026-final-.PNG'], [$file->filename, $file->mimeType, $file->size, $file->width, $file->height, $file->isImage(), $file->safeFilename()]);
        self::assertSame([false, 'rapport.pdf', null, null], [FileInfo::of('rapport.pdf', 'application/pdf', 0)->isImage(), FileInfo::of('rapport.pdf', 'application/pdf', 0)->safeFilename(), FileInfo::of('a', 'a/b', 0)->width, FileInfo::of('a', 'a/b', 0)->height]);
        self::assertSame(['file', 'file', 'a.b'], [FileInfo::of('ÉÉÉ', 'a/b', 1)->safeFilename(), FileInfo::of('...', 'a/b', 1)->safeFilename(), FileInfo::of('-a.b-', 'a/b', 1)->safeFilename()]);
        self::assertSame(str_repeat('é', 255), FileInfo::of(str_repeat('é', 255), 'image/svg+xml', 1, 1, 1)->filename);
        self::assertSame([1, 1], [FileInfo::of('a', 'a/b', 0, 1, 1)->width, FileInfo::of('a', 'a/b', 0, 1, 1)->height]);

        foreach ([
            [static fn (): FileInfo => FileInfo::of(' ', 'a/b', 1), 'A file name is 1 to 255 characters, without a slash; "" is not.'],
            [static fn (): FileInfo => FileInfo::of(str_repeat('é', 256), 'a/b', 1), 'A file name is 1 to 255 characters, without a slash; "'.str_repeat('é', 256).'" is not.'],
            [static fn (): FileInfo => FileInfo::of('a/b.png', 'a/b', 1), 'A file name is 1 to 255 characters, without a slash; "a/b.png" is not.'],
            [static fn (): FileInfo => FileInfo::of('a\\b.png', 'a/b', 1), 'A file name is 1 to 255 characters, without a slash; "a\\b.png" is not.'],
            [static fn (): FileInfo => FileInfo::of('a', 'image', 1), '"image" is not a media type.'],
            [static fn (): FileInfo => FileInfo::of('a', 'Image/png', 1), '"Image/png" is not a media type.'],
            [static fn (): FileInfo => FileInfo::of('a', "image/png\n", 1), "\"image/png\n\" is not a media type."],
            [static fn (): FileInfo => FileInfo::of('a', 'x/image/png', 1), '"x/image/png" is not a media type.'],
            [static fn (): FileInfo => FileInfo::of('a', 'a/b', -1), 'A size is 0 or more, and dimensions 1 or more.'],
            [static fn (): FileInfo => FileInfo::of('a', 'a/b', 1, 0, 1), 'A size is 0 or more, and dimensions 1 or more.'],
            [static fn (): FileInfo => FileInfo::of('a', 'a/b', 1, 1, 0), 'A size is 0 or more, and dimensions 1 or more.'],
        ] as [$make, $message]) {
            try {
                $make();
                self::fail($message);
            } catch (InvalidMedia $invalidMedia) {
                self::assertSame($message, $invalidMedia->getMessage());
                self::assertInstanceOf(MediaException::class, $invalidMedia);
            }
        }
    }

    public function testAMediumIsUploadedDescribedAndReplaced(): void
    {
        $collection = MediaCollection::create('events', '  Événements ');
        $at = new DateTimeImmutable('2026-11-04 07:00', new DateTimeZone('America/Toronto'));
        $media = Media::upload(FileInfo::of('fête.png', 'image/png', 1024, 400, 200), $collection, $at);

        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $media->id);
        self::assertSame([1, 'originals/'.$media->id.'/1/f-te.png', $collection, null, null, null, 'all-rights-reserved', AiDisclosure::None, [0.5, 0.5], '2026-11-04T12:00:00+00:00'], [$media->version(), $media->storageKey(), $media->collection(), $media->title(), $media->alt(), $media->credit(), $media->licenseId(), $media->aiDisclosure(), $media->focalPoint(), $media->updatedAt()->format('c')]);
        self::assertSame(['fête.png', 'image/png', 1024, 400, 200], [$media->file()->filename, $media->file()->mimeType, $media->file()->size, $media->file()->width, $media->file()->height]);
        self::assertSame(['events', 'Événements'], [$collection->key, $collection->name()]);

        $media->describe(' La fête ', ' Des gens qui dansent ', ' Ada ');
        $media->license('CC-BY-4.0', AiDisclosure::Assisted);
        $media->focus(0.25, 1.0);
        self::assertSame(['La fête', 'Des gens qui dansent', 'Ada', 'CC-BY-4.0', AiDisclosure::Assisted, [0.25, 1.0]], [$media->title(), $media->alt(), $media->credit(), $media->licenseId(), $media->aiDisclosure(), $media->focalPoint()]);
        $media->describe('', null, str_repeat('é', 255));
        self::assertSame([null, null, str_repeat('é', 255)], [$media->title(), $media->alt(), $media->credit()]);
        $media->describe(str_repeat('é', 255), str_repeat('é', 500));
        $media->license('CC0-1.0');
        self::assertSame(AiDisclosure::None, $media->aiDisclosure());
        $media->focus(0, 0);
        self::assertSame([0.0, 0.0], $media->focalPoint());
        $media->focus(1, 0.5);
        self::assertSame([1.0, 0.5], $media->focalPoint());

        $version = $media->replace(FileInfo::of('fête-2.jpg', 'image/jpeg', 2048, 800, 600), $at->modify('+1 day'));
        self::assertSame([2, 'originals/'.$media->id.'/2/f-te-2.jpg', 'fête-2.jpg', 'image/jpeg', 2048, 800, 600, '2026-11-05T12:00:00+00:00'], [$media->version(), $media->storageKey(), $media->file()->filename, $media->file()->mimeType, $media->file()->size, $media->file()->width, $media->file()->height, $media->updatedAt()->format('c')]);
        self::assertSame([$media, 1, 'fête.png', 'image/png', 1024, 'originals/'.$media->id.'/1/f-te.png', '2026-11-05T12:00:00+00:00'], [$version->media, $version->number, $version->filename, $version->mimeType, $version->size, $version->storageKey, $version->replacedAt()->format('c')]);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $version->id);

        $media->rename('  Fête, recadrée.jpg ');
        self::assertSame(['Fête, recadrée.jpg', 'image/jpeg', 2048, 800, 600, 'originals/'.$media->id.'/2/f-te-2.jpg'], [$media->file()->filename, $media->file()->mimeType, $media->file()->size, $media->file()->width, $media->file()->height, $media->storageKey()]);

        try {
            $media->rename('a/b.jpg');
            self::fail('Renamed.');
        } catch (InvalidMedia $invalidMedia) {
            self::assertSame('A file name is 1 to 255 characters, without a slash; "a/b.jpg" is not.', $invalidMedia->getMessage());
            self::assertSame('Fête, recadrée.jpg', $media->file()->filename);
        }

        $media->moveTo(null);
        self::assertNull($media->collection());
        $collection->rename('Fêtes');
        self::assertSame('Fêtes', $collection->name());
    }

    public function testBadValuesAreRefused(): void
    {
        $media = Media::upload(FileInfo::of('a.png', 'image/png', 1), null, new DateTimeImmutable());

        foreach ([
            [static fn () => $media->describe(str_repeat('é', 256)), 'The title of a medium is 255 characters or fewer.'],
            [static fn () => $media->describe(null, str_repeat('é', 501)), 'The alternative text of a medium is 500 characters or fewer.'],
            [static fn () => $media->describe(null, null, str_repeat('é', 256)), 'The credit of a medium is 255 characters or fewer.'],
            [static fn () => $media->license('CC BY'), 'A license is an SPDX identifier or "all-rights-reserved"; "CC BY" is not.'],
            [static fn () => $media->license(''), 'A license is an SPDX identifier or "all-rights-reserved"; "" is not.'],
            [static fn () => $media->license("MIT\n"), "A license is an SPDX identifier or \"all-rights-reserved\"; \"MIT\n\" is not."],
            [static fn () => $media->license(str_repeat('a', 65)), 'A license is an SPDX identifier or "all-rights-reserved"; "'.str_repeat('a', 65).'" is not.'],
            [static fn () => $media->focus(-0.01, 0.5), 'A focal point is from 0 to 1 across and down.'],
            [static fn () => $media->focus(1.01, 0.5), 'A focal point is from 0 to 1 across and down.'],
            [static fn () => $media->focus(0.5, -0.01), 'A focal point is from 0 to 1 across and down.'],
            [static fn () => $media->focus(0.5, 1.01), 'A focal point is from 0 to 1 across and down.'],
            [static fn (): MediaCollection => MediaCollection::create('Events', 'x'), 'A collection key is 1 to 64 lower-case letters, digits, "_" or "-", starting with a letter; "Events" is not.'],
            [static fn (): MediaCollection => MediaCollection::create("e\n", 'x'), "A collection key is 1 to 64 lower-case letters, digits, \"_\" or \"-\", starting with a letter; \"e\n\" is not."],
            [static fn (): MediaCollection => MediaCollection::create('1e', 'x'), 'A collection key is 1 to 64 lower-case letters, digits, "_" or "-", starting with a letter; "1e" is not.'],
            [static fn (): MediaCollection => MediaCollection::create('e'.str_repeat('a', 64), 'x'), 'A collection key is 1 to 64 lower-case letters, digits, "_" or "-", starting with a letter; "e'.str_repeat('a', 64).'" is not.'],
            [static fn (): MediaCollection => MediaCollection::create('e', ' '), 'A collection name is 1 to 150 characters.'],
            [static fn (): MediaCollection => MediaCollection::create('e', str_repeat('é', 151)), 'A collection name is 1 to 150 characters.'],
        ] as [$change, $message]) {
            try {
                $change();
                self::fail($message);
            } catch (InvalidMedia $invalidMedia) {
                self::assertSame($message, $invalidMedia->getMessage());
            }
        }

        self::assertSame(['e'.str_repeat('a', 63), str_repeat('é', 150)], [MediaCollection::create('e'.str_repeat('a', 63), 'x')->key, MediaCollection::create('e', str_repeat('é', 150))->name()]);
        $media->license(str_repeat('a', 64));
        self::assertSame(str_repeat('a', 64), $media->licenseId());
    }
}
