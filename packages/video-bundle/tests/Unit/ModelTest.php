<?php

declare(strict_types=1);

namespace Phil\VideoBundle\Tests\Unit;

use Closure;
use Phil\TimedText\Track;
use Phil\Url\Url;
use Phil\VideoBundle\Application\Exception\InvalidVideo;
use Phil\VideoBundle\Application\Heatmap;
use Phil\VideoBundle\Application\HostedVideo;
use Phil\VideoBundle\Application\LinkedVideo;
use Phil\VideoBundle\Application\TextTrack;
use Phil\VideoBundle\Application\TextTrackKind;
use Phil\VideoBundle\Application\Video;
use Phil\VideoBundle\Application\VideoSource;
use Phil\VideoBundle\Application\ViewingProgress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Video::class)]
#[CoversClass(HostedVideo::class)]
#[CoversClass(LinkedVideo::class)]
#[CoversClass(VideoSource::class)]
#[CoversClass(TextTrack::class)]
#[CoversClass(ViewingProgress::class)]
#[CoversClass(Heatmap::class)]
final class ModelTest extends TestCase
{
    private static function source(): VideoSource
    {
        return new VideoSource(Url::fromString('https://cdn.example.com/talk.mp4'));
    }

    public function testAHostedVideoHasItsFilesStreamAndTracks(): void
    {
        $track = new TextTrack(Url::fromString('https://cdn.example.com/talk.fr.vtt'), 'fr-CA', 'Français');
        $video = new HostedVideo('talk_1', 'The talk', [self::source()], 640, 360, hls: Url::fromString('https://cdn.example.com/talk.m3u8'), tracks: [$track], transcript: new Track(), transcriptLanguage: 'fr-CA');

        self::assertSame(['talk_1', 'The talk', 640, 360], [$video->id, $video->title, $video->width, $video->height]);
        self::assertSame('https://cdn.example.com/talk.m3u8', $video->hls?->toString());
        self::assertSame([TextTrackKind::Captions, false], [$track->kind, $track->default]);
        self::assertSame('video/mp4', self::source()->type);
        self::assertSame('fr-CA', $video->transcriptLanguage);
        self::assertSame([1280, 720], [new HostedVideo('a', 'A', [self::source()])->width, new HostedVideo('a', 'A', [self::source()])->height]);
        self::assertSame(1, new HostedVideo(str_repeat('a', 64), 'A', [self::source()], 1, 1)->width);
    }

    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function badVideos(): iterable
    {
        yield 'no source' => [static fn (): HostedVideo => new HostedVideo('a', 'A', []), 'A hosted video has a file, which a browser without HLS plays.'];
        yield 'bad identifier' => [static fn (): HostedVideo => new HostedVideo('a/b', 'A', [self::source()]), 'The identifier of a video holds letters, digits, "_" and "-", 64 at most.'];
        yield 'long identifier' => [static fn (): HostedVideo => new HostedVideo(str_repeat('a', 65), 'A', [self::source()]), 'The identifier of a video holds'];
        yield 'blank title' => [static fn (): HostedVideo => new HostedVideo('a', ' ', [self::source()]), 'A video has a title.'];
        yield 'no width' => [static fn (): HostedVideo => new HostedVideo('a', 'A', [self::source()], 0), 'A video is at least one pixel wide and high.'];
        yield 'no height' => [static fn (): HostedVideo => new HostedVideo('a', 'A', [self::source()], 1, 0), 'A video is at least one pixel wide and high.'];
        yield 'transcript language' => [static fn (): HostedVideo => new HostedVideo('a', 'A', [self::source()], transcriptLanguage: 'french'), '"french" is no language tag.'];
        yield 'source type' => [static fn (): VideoSource => new VideoSource(Url::fromString('https://e.example.com/a'), 'audio/mpeg'), '"audio/mpeg" is no video type.'];
        yield 'source type after a line' => [static fn (): VideoSource => new VideoSource(Url::fromString('https://e.example.com/a'), "video/mp4\n"), 'is no video type.'];
        yield 'track language' => [static fn (): TextTrack => new TextTrack(Url::fromString('https://e.example.com/a.vtt'), 'fr_CA', 'Fr'), '"fr_CA" is no language tag.'];
        yield 'identifier after a line' => [static fn (): HostedVideo => new HostedVideo("a\n", 'A', [self::source()]), 'The identifier of a video holds'];
        yield 'transcript language after a line' => [static fn (): HostedVideo => new HostedVideo('a', 'A', [self::source()], transcriptLanguage: "fr\n"), 'is no language tag.'];
        yield 'track language after a line' => [static fn (): TextTrack => new TextTrack(Url::fromString('https://e.example.com/a.vtt'), "fr\n", 'Fr'), 'is no language tag.'];
        yield 'blank track label' => [static fn (): TextTrack => new TextTrack(Url::fromString('https://e.example.com/a.vtt'), 'fr', ' '), 'A text track has a label.'];
        yield 'source type with a prefix' => [static fn (): VideoSource => new VideoSource(Url::fromString('https://e.example.com/a'), 'xvideo/mp4'), '"xvideo/mp4" is no video type.'];
        yield 'viewing identifier after a line' => [static fn (): ViewingProgress => ViewingProgress::fromString("a\n", str_repeat('1', 100)), 'The identifier of a video holds'];
        yield 'track label' => [static fn (): TextTrack => new TextTrack(Url::fromString('https://e.example.com/a.vtt'), 'fr', ''), 'A text track has a label.'];
    }

    /**
     * @param Closure(): mixed $build
     */
    #[DataProvider('badVideos')]
    public function testAVideoRefusesWhatNoPlayerShows(Closure $build, string $message): void
    {
        $this->expectException(InvalidVideo::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function links(): iterable
    {
        yield 'YouTube' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1', 'YouTube', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'];
        yield 'YouTube with a start' => ['https://youtu.be/dQw4w9WgXcQ?t=90', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=90&autoplay=1', 'YouTube', 'https://youtu.be/dQw4w9WgXcQ?t=90'];
        yield 'Vimeo' => ['https://vimeo.com/76979871', 'https://player.vimeo.com/video/76979871?dnt=1&autoplay=1', 'Vimeo', 'https://vimeo.com/76979871'];
    }

    #[DataProvider('links')]
    public function testALinkedVideoPlaysWithoutCookiesOncePressed(string $address, string $embed, string $platform, string $watch): void
    {
        $poster = Url::fromString('https://cdn.example.com/poster.jpg');
        $video = LinkedVideo::fromUrl('v1', 'A video', $address, $poster);

        self::assertSame($embed, $video->embed());
        self::assertSame($platform, $video->platform());
        self::assertSame($watch, $video->watch->toString());
        self::assertSame($poster, $video->poster);
        self::assertSame(['v1', 'A video', 1280, 720], [$video->id, $video->title, $video->width, $video->height]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badLinks(): iterable
    {
        yield 'not an address' => ['dQw4w9WgXcQ', 'is not an address'];
        yield 'another site' => ['https://example.com/watch?v=dQw4w9WgXcQ', 'The address is no video of YouTube or Vimeo.'];
        yield 'a channel' => ['https://www.youtube.com/@channel', 'The address is no video of YouTube or Vimeo.'];
    }

    #[DataProvider('badLinks')]
    public function testALinkedVideoIsAVideoOfYouTubeOrVimeo(string $address, string $message): void
    {
        $this->expectException(InvalidVideo::class);

        try {
            LinkedVideo::fromUrl('v', 'V', $address);
        } catch (InvalidVideo $invalidVideo) {
            if (!str_contains($message, 'is not an address')) {
                self::assertSame($message, $invalidVideo->getMessage());
            }

            throw $invalidVideo;
        }
    }

    public function testAViewingIsAHundredParts(): void
    {
        $progress = ViewingProgress::fromString('talk', '11'.str_repeat('0', 97).'1');

        self::assertSame([0, 1, 99], $progress->parts);
        self::assertSame(3, $progress->percent());
        self::assertFalse($progress->isComplete());
        self::assertSame('talk', $progress->videoId);
        self::assertTrue(ViewingProgress::fromString('t', str_repeat('1', 100))->isComplete());
        self::assertSame([], ViewingProgress::fromString(str_repeat('a', 64), str_repeat('0', 100))->parts);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badViewings(): iterable
    {
        yield 'identifier' => ['a b', str_repeat('0', 100)];
        yield 'long identifier' => [str_repeat('a', 65), str_repeat('0', 100)];
        yield 'too few parts' => ['a', str_repeat('0', 99)];
        yield 'too many parts' => ['a', str_repeat('0', 101)];
        yield 'not bits' => ['a', str_repeat('2', 100)];
        yield 'a line after' => ['a', str_repeat('0', 100)."\n"];
    }

    #[DataProvider('badViewings')]
    public function testAViewingIsRefusedOtherwise(string $video, string $parts): void
    {
        $this->expectException(InvalidVideo::class);

        ViewingProgress::fromString($video, $parts);
    }

    public function testAHeatMapSaysTheShareOfViewingsOfEachPart(): void
    {
        $percentages = new Heatmap(3, [3, 2, 1])->percentages();

        self::assertCount(100, $percentages);
        self::assertSame([100, 66, 33, 0], \array_slice($percentages, 0, 4));
        self::assertSame(array_fill(0, 100, 0), new Heatmap()->percentages());
    }

    public function testAVideoOfAnyKindIsAsLargeAsHdByDefault(): void
    {
        $video = new readonly class('a', 'A') extends Video {
        };

        self::assertSame([1280, 720], [$video->width, $video->height]);
    }

    public function testAHeatMapStartsWithoutViewings(): void
    {
        $heatmap = new Heatmap();

        self::assertSame([0, []], [$heatmap->viewings, $heatmap->counts]);
    }
}
