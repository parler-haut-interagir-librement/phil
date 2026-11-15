<?php

declare(strict_types=1);

namespace Phil\TimedText\Tests\Unit;

use Phil\TimedText\Chapter;
use Phil\TimedText\ChapterFormat;
use Phil\TimedText\ChapterList;
use Phil\TimedText\ChapterLocation;
use Phil\TimedText\Exception\InvalidCue;
use Phil\TimedText\Exception\InvalidTimedText;
use Phil\TimedText\Exception\LimitExceeded;
use Phil\TimedText\Limits;
use Phil\TimedText\Podcast\JsonChapterReader;
use Phil\TimedText\Podcast\JsonChapterWriter;
use Phil\TimedText\WebVtt\WebVttChapterReader;
use Phil\TimedText\WebVtt\WebVttChapterWriter;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Chapter::class)]
#[CoversClass(ChapterList::class)]
#[CoversClass(ChapterLocation::class)]
#[CoversClass(ChapterFormat::class)]
#[CoversClass(JsonChapterReader::class)]
#[CoversClass(JsonChapterWriter::class)]
#[CoversClass(WebVttChapterReader::class)]
#[CoversClass(WebVttChapterWriter::class)]
final class ChaptersTest extends TestCase
{
    private const string FIXTURE = __DIR__.'/../Fixtures/chapters.json';

    public function testItReadsPodcastChapters(): void
    {
        $list = new JsonChapterReader()->read((string) file_get_contents(self::FIXTURE));

        self::assertSame(['1.2.0', 'Episode 12', 'The hosts', 'A show', null, null, false], [$list->version, $list->title, $list->author, $list->podcastName, $list->description, $list->fileName, $list->waypoints]);
        self::assertSame(3, $list->count());

        [$intro, $interview, $sponsor] = $list->chapters;
        self::assertSame([0, 'Introduction', null, null, 'https://example.com/intro.jpg', true], [$intro->start, $intro->title, $intro->end, $intro->url, $intro->image?->toString(), $intro->toc]);
        self::assertSame([168_500, 2_400_000, 'https://example.com/guest'], [$interview->start, $interview->end, $interview->url?->toString()]);
        self::assertSame(['Montréal', 'geo:45.5,-73.6', 'R1634158'], [$interview->location?->name, $interview->location?->geo, $interview->location?->osm]);
        self::assertFalse($sponsor->toc);
        self::assertSame([$intro, $interview], $list->tableOfContents());
    }

    public function testItWritesBackWhatItRead(): void
    {
        $text = (string) file_get_contents(self::FIXTURE);

        self::assertSame($text, new JsonChapterWriter()->write(new JsonChapterReader()->read($text)));
    }

    public function testItWritesEveryFieldOfTheFile(): void
    {
        $list = new ChapterList(
            [new Chapter(0, '', location: new ChapterLocation('Here', 'geo:1,2'))],
            '1.2.0',
            'T',
            'A',
            'P',
            'D',
            'f.mp3',
            true,
        );
        $json = new JsonChapterWriter()->write($list);

        self::assertSame(
            "{\n    \"version\": \"1.2.0\",\n    \"title\": \"T\",\n    \"author\": \"A\",\n    \"podcastName\": \"P\",\n    \"description\": \"D\",\n    \"fileName\": \"f.mp3\",\n    \"waypoints\": true,\n    \"chapters\": [\n        {\n            \"startTime\": 0,\n            \"location\": {\n                \"name\": \"Here\",\n                \"geo\": \"geo:1,2\"\n            }\n        }\n    ]\n}\n",
            $json,
        );
        $read = new JsonChapterReader()->read($json);
        self::assertSame(['D', 'f.mp3', true, ''], [$read->description, $read->fileName, $read->waypoints, $read->chapters[0]->title]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalid(): iterable
    {
        yield 'no version' => ['{"chapters":[]}', 'The chapters name their "version".'];
        yield 'version as a number' => ['{"version":1,"chapters":[]}', '"version" is a string.'];
        yield 'waypoints' => ['{"version":"1.2.0","waypoints":"yes","chapters":[]}', '"waypoints" is true or false.'];
        yield 'title' => ['{"version":"1.2.0","title":3,"chapters":[]}', '"title" is a string.'];
        yield 'chapter as a list' => ['{"version":"1.2.0","chapters":[3]}', 'Chapter 1: a chapter is an object.'];
        yield 'no start' => ['{"version":"1.2.0","chapters":[{"title":"a"}]}', 'Chapter 1: "startTime" is a number of seconds from 0.'];
        yield 'negative end' => ['{"version":"1.2.0","chapters":[{"startTime":0,"endTime":-1}]}', 'Chapter 1: "endTime" is a number of seconds from its start.'];
        yield 'end before start' => ['{"version":"1.2.0","chapters":[{"startTime":5,"endTime":4}]}', 'Chapter 1: "endTime" is a number of seconds from its start.'];
        yield 'end as a string' => ['{"version":"1.2.0","chapters":[{"startTime":5,"endTime":"6"}]}', 'Chapter 1: "endTime" is a number of seconds from its start.'];
        yield 'toc' => ['{"version":"1.2.0","chapters":[{"startTime":0,"toc":1}]}', 'Chapter 1: "toc" is true or false.'];
        yield 'title of a chapter' => ['{"version":"1.2.0","chapters":[{"startTime":0,"title":[]}]}', 'Chapter 1: "title" is a string.'];
        yield 'script link' => ['{"version":"1.2.0","chapters":[{"startTime":0,"url":"javascript:alert(1)"}]}', 'Chapter 1: "url" is an http or https address.'];
        yield 'local image' => ['{"version":"1.2.0","chapters":[{"startTime":0,"img":"file:///etc/passwd"}]}', 'Chapter 1: "img" is an http or https address.'];
        yield 'location as a string' => ['{"version":"1.2.0","chapters":[{"startTime":0,"location":"here"}]}', 'Chapter 1: "location" is an object.'];
        yield 'location without geo' => ['{"version":"1.2.0","chapters":[{"startTime":0,"location":{"name":"here"}}]}', 'Chapter 1: a location has a "name" and a "geo" URI.'];
        yield 'location without name' => ['{"version":"1.2.0","chapters":[{"startTime":0,"location":{"geo":"geo:1,2"}}]}', 'Chapter 1: a location has a "name" and a "geo" URI.'];
        yield 'location with a link' => ['{"version":"1.2.0","chapters":[{"startTime":0,"location":{"name":"a","geo":"https://x.example"}}]}', 'Chapter 1: a location has a "name" and a "geo" URI.'];
        yield 'location with a numeric osm' => ['{"version":"1.2.0","chapters":[{"startTime":0,"location":{"name":"a","geo":"geo:1,2","osm":5}}]}', 'Chapter 1: location "osm" is a string.'];
        yield 'order' => ['{"version":"1.2.0","chapters":[{"startTime":5},{"startTime":4}]}', 'Chapter 2: it starts before the chapter above it.'];
    }

    #[DataProvider('invalid')]
    public function testItRefusesWhatIsNotPodcastChapters(string $json, string $message): void
    {
        $this->expectException(InvalidTimedText::class);
        $this->expectExceptionMessageIsOrContains($message);

        new JsonChapterReader()->read($json);
    }

    public function testTheLimitsBoundTheWork(): void
    {
        $json = '{"version":"1.2.0","chapters":[{"startTime":0,"title":"ab"},{"startTime":1}]}';

        self::assertSame(2, new JsonChapterReader(new Limits(maxCues: 2, maxTextBytes: 5))->read($json)->count());

        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains('The document holds more than 1 chapters.');

        new JsonChapterReader(new Limits(maxCues: 1))->read($json);
    }

    public function testItReadsAndWritesWebVttChapters(): void
    {
        $list = new WebVttChapterReader()->read("WEBVTT\n\n00:00.000 --> 00:10.000\n<b>Intro</b> &amp; more\n\n00:10.000 --> 00:20.000\nNext");

        self::assertSame([[0, 'Intro & more', 10_000], [10_000, 'Next', 20_000]], array_map(static fn (Chapter $chapter): array => [$chapter->start, $chapter->title, $chapter->end], $list->chapters));

        $written = new WebVttChapterWriter(30_000)->write(new ChapterList([new Chapter(0, 'A & <b>'), new Chapter(5_000, 'B', 8_000), new Chapter(10_000, 'C')]));

        self::assertSame("WEBVTT\n\n00:00:00.000 --> 00:00:05.000\nA &amp; &lt;b&gt;\n\n00:00:05.000 --> 00:00:08.000\nB\n\n00:00:10.000 --> 00:00:30.000\nC\n", $written);
        self::assertSame('A & <b>', new WebVttChapterReader()->read($written)->chapters[0]->title);
    }

    public function testWebVttChaptersReadStrictly(): void
    {
        $this->expectException(InvalidTimedText::class);

        new WebVttChapterReader(new Limits())->read("WEBVTT\n\nnonsense");
    }

    public function testTheLastWebVttChapterNeedsAnEnd(): void
    {
        self::assertSame("WEBVTT\n\n00:00:00.000 --> 00:00:01.000\nA\n", new WebVttChapterWriter()->write(new ChapterList([new Chapter(0, 'A', 1_000)])));

        $this->expectException(InvalidCue::class);
        $this->expectExceptionMessageIsOrContains('The last chapter has no end: give the duration of the media to the writer.');

        new WebVttChapterWriter()->write(new ChapterList([new Chapter(0, 'A')]));
    }

    public function testAListFindsTheChapterUnderWay(): void
    {
        $list = new ChapterList([new Chapter(1_000, 'A'), new Chapter(2_000, 'B', 3_000), new Chapter(4_000, 'C')]);

        self::assertNull($list->at(999));
        self::assertSame('A', $list->at(1_000)?->title);
        self::assertSame('B', $list->at(2_999)?->title);
        self::assertSame('A', $list->at(3_000)?->title);
        self::assertSame('C', $list->at(9_000)?->title);
        self::assertSame([[1_000, 2_000], [2_000, 3_000], [4_000, 5_000]], array_map(static fn (array $pair): array => [$pair[0]->start, $pair[1]], $list->withEnds(5_000)));
        self::assertNull($list->withEnds()[2][1]);
    }

    public function testAChapterKeepsItsValues(): void
    {
        $url = Url::fromString('https://example.com/a');
        $location = new ChapterLocation('Here', 'geo:1,2');
        $chapter = new Chapter(1_000, 'A', 2_000, $url, $url, false, $location);
        $moved = $chapter->shifted(500);

        self::assertSame([1_500, 'A', 2_500, $url, $url, false, $location], [$moved->start, $moved->title, $moved->end, $moved->url, $moved->image, $moved->toc, $moved->location]);
        self::assertNull(new Chapter(0, 'x')->shifted(5)->end);
        self::assertSame(0, new Chapter(0, 'x', 0)->end);
    }

    public function testAChapterEndsAfterItsStart(): void
    {
        $this->expectException(InvalidCue::class);
        $this->expectExceptionMessageIsOrContains('A chapter ends at 1 ms, before its start at 2 ms.');

        new Chapter(2, 'x', 1);
    }

    public function testAChapterEndIsAnInstant(): void
    {
        $this->expectException(InvalidCue::class);

        new Chapter(0, 'x', -1);
    }

    public function testTheFormatsOfChapters(): void
    {
        self::assertSame(ChapterFormat::Json, ChapterFormat::fromMediaType(' Application/JSON+Chapters; charset=utf-8'));
        self::assertSame(ChapterFormat::WebVtt, ChapterFormat::fromMediaType('text/vtt'));
        self::assertNull(ChapterFormat::fromMediaType('application/json'));
        self::assertInstanceOf(JsonChapterReader::class, ChapterFormat::Json->reader());
        self::assertInstanceOf(WebVttChapterReader::class, ChapterFormat::WebVtt->reader(new Limits()));
        self::assertInstanceOf(JsonChapterWriter::class, ChapterFormat::Json->writer());
        self::assertSame("WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nA\n", ChapterFormat::WebVtt->writer(2_000)->write(new ChapterList([new Chapter(0, 'A')])));
        self::assertSame(1, ChapterFormat::Json->reader(new Limits(maxCues: 1))->read('{"version":"1","chapters":[{"startTime":0}]}')->count());
    }
}
