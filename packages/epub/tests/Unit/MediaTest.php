<?php

declare(strict_types=1);

namespace Phil\Epub\Tests\Unit;

use Closure;
use Phil\Epub\Asset;
use Phil\Epub\AssetKind;
use Phil\Epub\Chapter;
use Phil\Epub\EpubWriter;
use Phil\Epub\Exception\InvalidBook;
use Phil\Epub\Internal\Assembler;
use Phil\Epub\Internal\ManifestItem;
use Phil\Epub\Internal\PackageDocument;
use Phil\Epub\Tests\Unit\Fixture\Archive;
use Phil\Epub\Tests\Unit\Fixture\Books;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Asset::class)]
#[CoversClass(AssetKind::class)]
#[CoversClass(Assembler::class)]
#[CoversClass(ManifestItem::class)]
#[CoversClass(PackageDocument::class)]
final class MediaTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function audio(): iterable
    {
        yield 'MP3 with ID3' => [Books::MP3, 'audio/mpeg'];
        yield 'MP3 frame' => ["\xFF\xFB\x90\x00", 'audio/mpeg'];
        yield 'MP3 frame, lowest sync' => ["\xFF\xE0", 'audio/mpeg'];
        yield 'MP4' => [Books::MP4, 'audio/mp4'];
        yield 'Opus' => ['OggS'.str_repeat("\0", 24).'OpusHead', 'audio/ogg; codecs=opus'];
        yield 'Opus ending at the sixty-fourth byte' => ['OggS'.str_repeat("\0", 52).'OpusHead', 'audio/ogg; codecs=opus'];
    }

    #[DataProvider('audio')]
    public function testAnAudioFileIsKnownByItsBytes(string $bytes, string $type): void
    {
        $audio = Asset::audio('audio/a', $bytes);

        self::assertSame($type, $audio->mediaType);
        self::assertSame(AssetKind::Audio, $audio->kind);
        self::assertTrue($audio->isCore());
        self::assertNull($audio->fallback);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function foreignAudio(): iterable
    {
        yield 'Vorbis' => ['OggS'.str_repeat("\0", 24).'vorbis', 'audio/ogg'];
        yield 'Opus past the first page' => ['OggS'.str_repeat("\0", 61).'OpusHead', 'audio/ogg'];
        yield 'FLAC' => ['fLaC', 'audio/flac'];
        yield 'WAV' => ['RIFF0000WAVE', 'audio/wav'];
        yield 'WAV with data after the format' => ['RIFF0000WAVEfmt ', 'audio/wav'];
        yield 'Opus ending at the sixty-fifth byte' => ['OggS'.str_repeat("\0", 53).'OpusHead', 'audio/ogg'];
    }

    #[DataProvider('foreignAudio')]
    public function testAnAudioFileOutsideTheCoreTypesNeedsAFallback(string $bytes, string $type): void
    {
        $audio = Asset::audio('audio/a', $bytes, fallback: Asset::audio('audio/b.mp3', Books::MP3));

        self::assertSame($type, $audio->mediaType);
        self::assertFalse($audio->isCore());

        $this->expectExceptionObject(InvalidBook::because(\sprintf('The audio file "audio/a" is a "%s" file, outside the EPUB core media types: it needs a fallback chain that ends in MP3, AAC or Opus.', $type)));
        Asset::audio('audio/a', $bytes);
    }

    public function testAFallbackChainEndsInACoreType(): void
    {
        $chain = Asset::audio('audio/a.flac', 'fLaC', fallback: Asset::audio('audio/b.wav', 'RIFF0000WAVE', fallback: Asset::audio('audio/c.mp3', Books::MP3)));

        self::assertSame('audio/c.mp3', $chain->fallback?->fallback?->path);

        $this->expectExceptionObject(InvalidBook::because('The audio file "audio/b.wav" is a "audio/wav" file, outside the EPUB core media types: it needs a fallback chain that ends in MP3, AAC or Opus.'));
        Asset::audio('audio/a.flac', 'fLaC', fallback: Asset::audio('audio/b.wav', 'RIFF0000WAVE'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function video(): iterable
    {
        yield 'MP4' => [Books::MP4, 'video/mp4'];
        yield 'WebM' => ["\x1A\x45\xDF\xA3", 'video/webm'];
        yield 'Ogg' => ['OggS', 'video/ogg'];
    }

    #[DataProvider('video')]
    public function testAVideoFileIsKnownByItsBytesAndNeedsNoFallback(string $bytes, string $type): void
    {
        $video = Asset::video('video/v', $bytes);

        self::assertSame($type, $video->mediaType);
        self::assertSame(AssetKind::Video, $video->kind);
        self::assertFalse($video->isCore());
    }

    public function testATextTrackIsWebVtt(): void
    {
        $track = Asset::textTrack('video/v.vtt', "\u{FEFF}".Books::VTT);

        self::assertSame('text/vtt', $track->mediaType);
        self::assertSame(AssetKind::TextTrack, $track->kind);
        self::assertTrue($track->isCore());
    }

    /**
     * @return iterable<string, array{Closure(): Asset, string}>
     */
    public static function invalid(): iterable
    {
        yield 'unknown audio' => [static fn (): Asset => Asset::audio('audio/a', 'nothing'), 'The audio file "audio/a" is not an MP3, MP4, Ogg, FLAC or WAV file.'];
        yield 'MP3 sync too low' => [static fn (): Asset => Asset::audio('audio/a', "\xFF\xDF"), 'The audio file "audio/a" is not an MP3, MP4, Ogg, FLAC or WAV file.'];
        yield 'MP3 sync not first' => [static fn (): Asset => Asset::audio('audio/a', "x\xFF\xFB"), 'The audio file "audio/a" is not an MP3, MP4, Ogg, FLAC or WAV file.'];
        yield 'RIFF that is not WAVE' => [static fn (): Asset => Asset::audio('audio/a', 'RIFF0000AVI '), 'The audio file "audio/a" is not an MP3, MP4, Ogg, FLAC or WAV file.'];
        yield 'unknown video' => [static fn (): Asset => Asset::video('video/v', 'nothing'), 'The video file "video/v" is not an MP4, WebM or Ogg file.'];
        yield 'not WebVTT' => [static fn (): Asset => Asset::textTrack('video/v.vtt', '1'."\n".'00:00:00,000 --> 00:00:01,000'), 'The text track "video/v.vtt" is not a WebVTT file: it starts with "WEBVTT".'];
        yield 'audio given a video type' => [static fn (): Asset => Asset::audio('audio/a', Books::MP4, 'video/mp4'), '"video/mp4" is not an EPUB core media type for a audio (audio/a).'];
        yield 'video given an audio type' => [static fn (): Asset => Asset::video('video/v', Books::MP4, 'audio/mp4'), '"audio/mp4" is not an EPUB core media type for a video (video/v).'];
        yield 'malformed media type' => [static fn (): Asset => Asset::video('video/v', Books::MP4, 'video/mp4 x'), '"video/mp4 x" is not an EPUB core media type for a video (video/v).'];
        yield 'image given a foreign type' => [static fn (): Asset => Asset::image('images/i', Books::PNG, 'image/tiff'), '"image/tiff" is not an EPUB core media type for a image (images/i).'];
        yield 'fallback of another kind' => [static fn (): Asset => Asset::video('video/v', Books::MP4, fallback: Asset::audio('audio/a.mp3', Books::MP3)), 'The fallback of "video/v" is another video file; "audio/a.mp3" is a audio.'];
        yield 'fallback to itself' => [static fn (): Asset => Asset::video('video/v', Books::MP4, fallback: Asset::video('video/v', Books::MP4)), 'The fallback of "video/v" is another video file; "video/v" is a video.'];
    }

    /**
     * @param Closure(): Asset $asset
     */
    #[DataProvider('invalid')]
    public function testAnInvalidMediaFileIsRefused(Closure $asset, string $message): void
    {
        $this->expectExceptionObject(InvalidBook::because($message));
        $asset();
    }

    public function testAMediaTypeWithCodecsIsKept(): void
    {
        self::assertSame('video/webm; codecs="vp9,opus"', Asset::video('video/v', "\x1A\x45\xDF\xA3", 'video/webm; codecs="vp9,opus"')->mediaType);
    }

    public function testMediaFilesAreInTheManifestWithTheirFallbacksAndAccessModes(): void
    {
        $mp3 = Asset::audio('audio/b.mp3', Books::MP3);
        $book = Books::book()
            ->withAsset(Asset::audio('audio/a.flac', 'fLaC', fallback: $mp3), $mp3, Asset::video('video/v.webm', "\x1A\x45\xDF\xA3", fallback: Asset::video('video/v.mp4', Books::MP4)), Asset::textTrack('video/v.vtt', Books::VTT))
            ->withChapter(new Chapter('<audio controls="" src="audio/a.flac">Listen</audio><video controls="" src="video/v.webm"><track kind="captions" src="video/v.vtt" srclang="en"/></video>'));
        $archive = Archive::read(new EpubWriter()->toString($book));
        $package = $archive->file('EPUB/package.opf');

        self::assertStringContainsString(<<<'XML'
                <item id="asset-1" href="audio/a.flac" media-type="audio/flac" fallback="asset-2"/>
                <item id="asset-2" href="audio/b.mp3" media-type="audio/mpeg"/>
                <item id="asset-3" href="video/v.webm" media-type="video/webm" fallback="asset-5"/>
                <item id="asset-4" href="video/v.vtt" media-type="text/vtt"/>
                <item id="asset-5" href="video/v.mp4" media-type="video/mp4"/>
            XML, $package);
        self::assertStringContainsString(<<<'XML'
                <meta property="schema:accessMode">textual</meta>
                <meta property="schema:accessMode">visual</meta>
                <meta property="schema:accessMode">auditory</meta>
            XML, $package);
        self::assertSame('<audio controls="" src="audio/a.flac">Listen</audio><video controls="" src="video/v.webm"><track kind="captions" src="video/v.vtt" srclang="en"/></video>', $archive->body('chapter-1.xhtml'));
    }

    public function testAudioAloneIsAuditoryAndVideoAloneIsBoth(): void
    {
        $audio = Archive::read(new EpubWriter()->toString(Books::book()->withAsset(Asset::audio('a.mp3', Books::MP3))->withChapter(new Chapter('<p>x</p>'))))->file('EPUB/package.opf');
        $video = Archive::read(new EpubWriter()->toString(Books::book()->withAsset(Asset::video('v.mp4', Books::MP4))->withChapter(new Chapter('<p>x</p>'))))->file('EPUB/package.opf');

        self::assertStringContainsString('<meta property="schema:accessMode">auditory</meta>', $audio);
        self::assertStringNotContainsString('<meta property="schema:accessMode">visual</meta>', $audio);
        self::assertStringContainsString('<meta property="schema:accessMode">visual</meta>'."\n".'    <meta property="schema:accessMode">auditory</meta>', $video);
    }

    public function testAFallbackGivenTwiceIsWrittenOnce(): void
    {
        $mp3 = Asset::audio('audio/b.mp3', Books::MP3);
        $book = Books::book()
            ->withAsset(Asset::audio('audio/a.flac', 'fLaC', fallback: $mp3), Asset::audio('audio/c.wav', 'RIFF0000WAVE', fallback: $mp3))
            ->withChapter(new Chapter('<p>x</p>'));

        self::assertSame(['mimetype', 'META-INF/container.xml', 'EPUB/nav.xhtml', 'EPUB/chapter-1.xhtml', 'EPUB/audio/a.flac', 'EPUB/audio/c.wav', 'EPUB/audio/b.mp3', 'EPUB/package.opf'], Archive::read(new EpubWriter()->toString($book))->names());
    }

    public function testAFallbackCannotTakeThePathOfAnotherFile(): void
    {
        $book = Books::book()
            ->withAsset(Asset::audio('audio/a.flac', 'fLaC', fallback: Asset::audio('audio/b.mp3', Books::MP3)), Asset::audio('audio/b.mp3', Books::MP3))
            ->withChapter(new Chapter('<p>x</p>'));

        $this->expectExceptionObject(InvalidBook::because('The path "audio/b.mp3" is used twice.'));
        new EpubWriter()->toString($book);
    }
}
