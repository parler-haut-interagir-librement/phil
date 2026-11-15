<?php

declare(strict_types=1);

namespace Phil\TimedText\Tests\Benchmark;

use Generator;
use Phil\TimedText\Cue;
use Phil\TimedText\CueText;
use Phil\TimedText\Track;
use Phil\TimedText\TranscriptFormat;

/**
 * A transcript is read when an episode is published or a caption file uploaded, and written at each download of
 * another format. A three-hour episode, one cue every three seconds — 3 600 cues with two speakers —, must be read and
 * written in each format in a few tens of milliseconds, and shifted or split in a few.
 *
 * @BeforeMethods("setUp")
 */
final class TimedTextBench
{
    private const int HOURS = 3;

    private readonly Track $track;

    private string $document = '';

    private TranscriptFormat $format = TranscriptFormat::WebVtt;

    public function __construct()
    {
        $cues = [];

        for ($start = 0; $start < self::HOURS * 3_600_000; $start += 3_000) {
            $speaker = 0 === $start % 2 ? 'Alice' : 'Bob';
            $cues[] = new Cue($start, $start + 2_900, CueText::fromPlain("A line of the conversation & its reply\nnumber ".$start, $speaker));
        }

        $this->track = new Track($cues);
    }

    /**
     * @param array{format: string} $params
     */
    public function setUp(array $params): void
    {
        $this->format = TranscriptFormat::from($params['format']);
        $this->document = $this->format->writer()->write($this->track);
    }

    /**
     * @ParamProviders("formats")
     *
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchRead(): void
    {
        $this->format->reader()->read($this->document);
    }

    /**
     * @ParamProviders("formats")
     *
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchWrite(): void
    {
        $this->format->writer()->write($this->track);
    }

    /**
     * @ParamProviders("webVtt")
     *
     * @Revs(10)
     *
     * @Iterations(3)
     */
    public function benchShiftAndSplit(): void
    {
        $this->track->shifted(-1_500)->splitAt(5_400_000, true);
    }

    /**
     * @return Generator<string, array{format: string}>
     */
    public function formats(): Generator
    {
        foreach (TranscriptFormat::cases() as $format) {
            yield $format->name => ['format' => $format->value];
        }
    }

    /**
     * @return Generator<string, array{format: string}>
     */
    public function webVtt(): Generator
    {
        yield 'WebVtt' => ['format' => TranscriptFormat::WebVtt->value];
    }
}
