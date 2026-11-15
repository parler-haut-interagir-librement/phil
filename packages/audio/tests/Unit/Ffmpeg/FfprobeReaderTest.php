<?php

declare(strict_types=1);

namespace Phil\Audio\Tests\Unit\Ffmpeg;

use Phil\Audio\Exception\InvalidAudio;
use Phil\Audio\Exception\LimitExceeded;
use Phil\Audio\Ffmpeg\FfprobeReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FfprobeReader::class)]
final class FfprobeReaderTest extends TestCase
{
    public function testTheReportOfAFileIsRead(): void
    {
        $metadata = new FfprobeReader()->read((string) file_get_contents(__DIR__.'/../../Fixtures/ffprobe.json'));

        self::assertSame(215_042, $metadata->duration);
        self::assertSame('mp3', $metadata->container);
        self::assertSame('mp3', $metadata->codec);
        self::assertSame(128_512, $metadata->bitrate);
        self::assertSame(44_100, $metadata->sampleRate);
        self::assertSame(2, $metadata->channels);
        self::assertSame(['title' => 'The first episode', 'artist' => 'Ada & Grace', 'encoder' => 'LAME3.100'], $metadata->tags);
    }

    public function testTheStreamGivesWhatTheContainerDoesNot(): void
    {
        $metadata = new FfprobeReader()->read('{"streams":[{"codec_type":"video"},{"codec_type":"audio","duration":"1.5","bit_rate":96000,"sample_rate":"0","channels":0}],"format":{}}');

        self::assertSame(1_500, $metadata->duration);
        self::assertSame(96_000, $metadata->bitrate);
        self::assertSame('unknown', $metadata->container);
        self::assertNull($metadata->codec);
        self::assertNull($metadata->sampleRate);
        self::assertNull($metadata->channels);
        self::assertSame([], $metadata->tags);
    }

    public function testWhatIsNotANumberIsUnknown(): void
    {
        $metadata = new FfprobeReader()->read('{"streams":[{"codec_type":"audio","duration":"N/A","sample_rate":"-1","channels":"2","codec_name":""}],"format":{"duration":-3,"bit_rate":"1e9","format_name":5}}');

        self::assertNull($metadata->duration);
        self::assertNull($metadata->bitrate);
        self::assertNull($metadata->sampleRate);
        self::assertNull($metadata->channels);
        self::assertNull($metadata->codec);
        self::assertSame('unknown', $metadata->container);
    }

    public function testTheLargestNumbersAreBounded(): void
    {
        $metadata = new FfprobeReader()->read('{"streams":[{"codec_type":"audio","duration":"999999999999.999999999","sample_rate":1}],"format":{"bit_rate":999999999999999}}');

        self::assertSame(1_000_000_000_000_000, $metadata->duration);
        self::assertSame(999_999_999_999_999, $metadata->bitrate);
        self::assertSame(1, $metadata->sampleRate);

        $over = new FfprobeReader()->read('{"streams":[{"codec_type":"audio","duration":"1234567890123456"}],"format":{"bit_rate":1000000000000000}}');
        self::assertNull($over->duration);
        self::assertNull($over->bitrate);
        self::assertSame(1, new FfprobeReader()->read('{"streams":[{"codec_type":"audio","duration":"0.001","bit_rate":0}]}')->duration);
        self::assertSame(0, new FfprobeReader()->read('{"streams":[{"codec_type":"audio","bit_rate":0}]}')->bitrate);
        self::assertSame(2, new FfprobeReader()->read('{"streams":[{"codec_type":"audio","duration":"0.0015"}]}')->duration);
    }

    public function testTagsAreBounded(): void
    {
        $tags = [];

        for ($index = 0; $index < 70; ++$index) {
            $tags['tag'.$index] = 'value';
        }

        $tags['long'] = str_repeat('a', FfprobeReader::MAX_TAG_BYTES + 1);
        $report = ['streams' => [['codec_type' => 'audio', 'tags' => ['TITLE' => 'stream', 'bad' => "\xFF", 'number' => 3, 'edge' => str_repeat('b', FfprobeReader::MAX_TAG_BYTES)]]], 'format' => ['tags' => $tags]];
        $metadata = new FfprobeReader()->read(json_encode($report, \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_IGNORE));

        self::assertCount(FfprobeReader::MAX_TAGS, $metadata->tags);
        self::assertSame('value', $metadata->tag('tag63'));
        self::assertNull($metadata->tag('tag64'));
        self::assertNull($metadata->tag('long'));

        $few = new FfprobeReader()->read(json_encode(['streams' => $report['streams']], \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_IGNORE));
        self::assertSame(['title' => 'stream', 'edge' => str_repeat('b', FfprobeReader::MAX_TAG_BYTES)], $few->tags);

        $empty = new FfprobeReader()->read('{"streams":[{"codec_type":"audio","tags":{"":"x","é":"é","t":""}}]}');
        self::assertSame(['é' => 'é'], $empty->tags);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badReports(): iterable
    {
        yield 'not JSON' => ['nope', 'The report of ffprobe is no JSON: Syntax error'];
        yield 'too deep' => [str_repeat('[', 17).str_repeat(']', 17), 'The report of ffprobe is no JSON: Maximum stack depth exceeded'];
        yield 'a string' => ['"x"', 'The file holds no audio stream.'];
        yield 'no streams' => ['{"format":{"duration":"1.0"}}', 'The file holds no audio stream.'];
        yield 'streams not a list' => ['{"streams":"audio"}', 'The file holds no audio stream.'];
        yield 'video only' => ['{"streams":[{"codec_type":"video"},"audio"]}', 'The file holds no audio stream.'];
    }

    #[DataProvider('badReports')]
    public function testAReportWithoutAudioIsRefused(string $json, string $message): void
    {
        $this->expectException(InvalidAudio::class);
        $this->expectExceptionMessageIsOrContains($message);

        new FfprobeReader()->read($json);
    }

    public function testAReportPastItsBytesIsRefused(): void
    {
        $json = '{"streams":[{"codec_type":"audio"}]}';
        self::assertSame('unknown', new FfprobeReader(\strlen($json))->read($json)->container);

        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains('The report of ffprobe holds more than 10 bytes.');
        new FfprobeReader(10)->read($json);
    }
}
