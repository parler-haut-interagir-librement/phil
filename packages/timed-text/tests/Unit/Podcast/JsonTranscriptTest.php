<?php

declare(strict_types=1);

namespace Phil\TimedText\Tests\Unit\Podcast;

use Phil\TimedText\Cue;
use Phil\TimedText\Exception\InvalidTimedText;
use Phil\TimedText\Exception\LimitExceeded;
use Phil\TimedText\Internal\Json;
use Phil\TimedText\Issue;
use Phil\TimedText\Limits;
use Phil\TimedText\Podcast\JsonTranscriptReader;
use Phil\TimedText\Podcast\JsonTranscriptWriter;
use Phil\TimedText\Track;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonTranscriptReader::class)]
#[CoversClass(JsonTranscriptWriter::class)]
#[CoversClass(Json::class)]
final class JsonTranscriptTest extends TestCase
{
    private const string FIXTURE = __DIR__.'/../../Fixtures/transcript.json';

    public function testItReadsATranscript(): void
    {
        $track = new JsonTranscriptReader()->read((string) file_get_contents(self::FIXTURE));

        self::assertSame([[500, 2_000], [2_000, 3_250]], array_map(static fn (Cue $cue): array => [$cue->start, $cue->end], $track->cues));
        self::assertSame(['<v Alice>Hello &amp; welcome &lt;everyone&gt;.', 'Music plays.'], array_map(static fn (Cue $cue): string => $cue->text, $track->cues));
        self::assertSame(['Alice', null], array_map(static fn (Cue $cue): ?string => $cue->speaker(), $track->cues));
    }

    public function testItWritesBackWhatItRead(): void
    {
        $text = (string) file_get_contents(self::FIXTURE);

        self::assertSame($text, new JsonTranscriptWriter()->write(new JsonTranscriptReader()->read($text)));
    }

    public function testItWritesPlainBodies(): void
    {
        $json = new JsonTranscriptWriter()->write(new Track([new Cue(1, 1_000, "<v Bob>a <i>b</i>/é\nc")]));

        self::assertSame("{\n    \"version\": \"1.0.0\",\n    \"segments\": [\n        {\n            \"speaker\": \"Bob\",\n            \"startTime\": 0.001,\n            \"endTime\": 1,\n            \"body\": \"a b/é\\nc\"\n        }\n    ]\n}\n", $json);
        self::assertSame("{\n    \"version\": \"1.0.0\",\n    \"segments\": []\n}\n", new JsonTranscriptWriter()->write(new Track()));
    }

    public function testAnInspectionListsTheProblemsAndKeepsTheRest(): void
    {
        $json = json_encode(['segments' => [
            'not an object',
            ['endTime' => 1, 'body' => 'a'],
            ['startTime' => '1', 'endTime' => 1, 'body' => 'a'],
            ['startTime' => 1, 'body' => 'a'],
            ['startTime' => -1, 'endTime' => 1, 'body' => 'a'],
            ['startTime' => 2, 'endTime' => 1, 'body' => 'a'],
            ['startTime' => 1, 'endTime' => 2],
            ['startTime' => 1, 'endTime' => 2, 'body' => 3],
            ['startTime' => 1, 'endTime' => 2, 'body' => 'a', 'speaker' => 4],
            ['startTime' => 5, 'endTime' => 6, 'body' => 'kept', 'speaker' => null],
            ['startTime' => 1, 'endTime' => 2, 'body' => 'early'],
            ['startTime' => 3, 'endTime' => 3, 'body' => ''],
        ]], \JSON_THROW_ON_ERROR);

        $inspection = new JsonTranscriptReader()->inspect($json);

        self::assertSame([
            'Segment 1: a segment is an object.',
            'Segment 2: "startTime" is a number of seconds from 0.',
            'Segment 3: "startTime" is a number of seconds from 0.',
            'Segment 4: "endTime" is a number of seconds from 0.',
            'Segment 5: "startTime" is a number of seconds from 0.',
            'Segment 6: it ends before it starts.',
            'Segment 7: "body" is a string.',
            'Segment 8: "body" is a string.',
            'Segment 9: "speaker" is a string.',
            'Segment 11: it starts before the segment above it.',
        ], array_map(static fn (Issue $issue): string => $issue->toString(), $inspection->issues));
        self::assertSame(['kept', 'early', ''], array_map(static fn (Cue $cue): string => $cue->text, $inspection->track->cues));
        self::assertSame([null, null], [$inspection->issues[0]->line, $inspection->issues[9]->line]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function documents(): iterable
    {
        yield 'not JSON' => ['{', 'The document is not JSON, or nests deeper than 8 levels.'];
        yield 'too deep' => ['{"segments":[[[[[[[[[1]]]]]]]]]}', 'The document is not JSON, or nests deeper than 8 levels.'];
        yield 'a list' => ['[]', 'The document is a JSON object with a "segments" list.'];
        yield 'a string' => ['"segments"', 'The document is a JSON object with a "segments" list.'];
        yield 'no segments' => ['{"version":"1.0.0"}', 'The document is a JSON object with a "segments" list.'];
        yield 'segments as an object' => ['{"segments":{"a":1}}', 'The document is a JSON object with a "segments" list.'];
        yield 'segments as a string' => ['{"segments":"a"}', 'The document is a JSON object with a "segments" list.'];
        yield 'first problem' => ['{"segments":[{"startTime":1,"endTime":0,"body":""}]}', 'Segment 1: it ends before it starts.'];
    }

    #[DataProvider('documents')]
    public function testItRefusesWhatIsNotATranscript(string $json, string $message): void
    {
        try {
            new JsonTranscriptReader()->read($json);
            self::fail('Read as a transcript: '.$json);
        } catch (InvalidTimedText $invalidTimedText) {
            self::assertSame($message, $invalidTimedText->getMessage());
            self::assertNull($invalidTimedText->documentLine);
        }
    }

    public function testTheLimitsBoundTheWork(): void
    {
        $json = '{"segments":[{"startTime":0,"endTime":1,"body":"ab"},{"startTime":1,"endTime":2,"body":"cd"}]}';

        self::assertCount(2, new JsonTranscriptReader(new Limits(maxCues: 2, maxTextBytes: 2))->read($json)->cues);

        foreach ([
            [new Limits(maxCues: 1), 'The document holds more than 1 segments.'],
            [new Limits(maxBytes: 10), 'The document is 94 bytes; the limit is 10.'],
            [new Limits(maxTextBytes: 1), 'A text is longer than 1 bytes.'],
        ] as [$limits, $message]) {
            try {
                new JsonTranscriptReader($limits)->read($json);
                self::fail('Read past the limits: '.$message);
            } catch (LimitExceeded $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    public function testABigIntegerIsNotASecond(): void
    {
        $this->expectException(InvalidTimedText::class);
        $this->expectExceptionMessageIsOrContains('Segment 1: "startTime" is a number of seconds from 0.');

        new JsonTranscriptReader()->read('{"segments":[{"startTime":99999999999999999999999,"endTime":1,"body":""}]}');
    }
}
