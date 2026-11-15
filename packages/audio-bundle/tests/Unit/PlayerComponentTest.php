<?php

declare(strict_types=1);

namespace Phil\AudioBundle\Tests\Unit;

use LogicException;
use Phil\Audio\Audio;
use Phil\Audio\AudioFormat;
use Phil\Audio\AudioSource;
use Phil\Audio\AudioTranscript;
use Phil\Audio\Excerpt;
use Phil\Audio\ListeningPolicy;
use Phil\Audio\Peaks;
use Phil\AudioBundle\Infrastructure\Twig\PlayerComponent;
use Phil\TimedText\Chapter;
use Phil\TimedText\ChapterList;
use Phil\TimedText\Cue;
use Phil\TimedText\Track;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PlayerComponent::class)]
final class PlayerComponentTest extends TestCase
{
    public function testTheRecipesAreNamedUnderTheSubdirectoryOfTheKit(): void
    {
        self::assertSame('Button', new PlayerComponent()->kit('Button'));
        self::assertSame('Button', new PlayerComponent('/')->kit('Button'));
        self::assertSame('ui:Button', new PlayerComponent('ui/')->kit('Button'));
        self::assertSame('kits:phil:Button', new PlayerComponent('/kits/phil/')->kit('Button'));
    }

    public function testAPlayerIsDrawnWithATrack(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('The player is drawn with a track: give it "audio".');

        new PlayerComponent()->getAudio();
    }

    public function testTheWaveformHasTheBarsAsked(): void
    {
        $player = new PlayerComponent();
        $player->mount(new Audio('T', [], peaks: new Peaks(8_000, 1, 8, [0, 64, 0, 127, 0, 32])));
        self::assertSame(120, $player->bars);
        self::assertSame([0.5, 0.992, 0.25], $player->getAmplitudes());

        $player->bars = 2;
        self::assertSame([0.5, 0.992], $player->getAmplitudes());

        $player->bars = 0; // one bar at least
        self::assertSame([0.992], $player->getAmplitudes());
        self::assertSame('phil-audio', $player->id);
        self::assertFalse($player->entitled);
    }

    public function testTheInstantsOfAnExcerptStartAtItsStart(): void
    {
        $player = new PlayerComponent();
        $player->mount(new Audio(
            'T',
            [new AudioSource(Url::fromString('https://e.example.com/x.mp3'), AudioFormat::Mp3, true)],
            ListeningPolicy::excerpt(new Excerpt(60_000, 60_000)),
            chapters: new ChapterList([new Chapter(59_999, 'Before'), new Chapter(60_000, 'First'), new Chapter(119_999, 'Last'), new Chapter(120_000, 'After')]),
            transcripts: [new AudioTranscript(new Track([new Cue(60_500, 61_250, 'a')]), 'en', 'English', Url::fromString('https://e.example.com/t.vtt'))],
        ));

        self::assertTrue($player->isExcerpt());
        self::assertSame('excerpt', $player->getMode());
        self::assertSame([['0.000', 'First'], ['59.999', 'Last']], array_map(static fn (array $chapter): array => [$chapter['start'], $chapter['title']], $player->getChapters()));
        $cue = $player->getTranscripts()[0]['cues'][0];
        self::assertSame(['0.500', '1.250', '1:00', 'PT60.5S'], [$cue['start'], $cue['end'], $cue['clock'], $cue['datetime']]);
        self::assertNull($player->getTranscripts()[0]['url']);

        $player->entitled = true;
        self::assertFalse($player->isExcerpt());
        self::assertCount(4, $player->getChapters());
        self::assertSame('60.500', $player->getTranscripts()[0]['cues'][0]['start']);
        self::assertSame('https://e.example.com/t.vtt', $player->getTranscripts()[0]['url']);
        self::assertSame([], $player->getSources());
    }

    public function testATrackWithoutPeaksHasNoWaveform(): void
    {
        $player = new PlayerComponent();
        $player->mount(new Audio('T', []));

        self::assertSame([], $player->getAmplitudes());
    }

    public function testEveryTranscriptIsListedWithItsCuesAndItsOwnFields(): void
    {
        $player = new PlayerComponent();
        $player->mount(new Audio('T', [], transcripts: [
            new AudioTranscript(new Track([new Cue(1_000_000, 1_001_250, 'a'), new Cue(1_002_000, 1_003_000, 'b')]), 'en', 'English', Url::fromString('https://e.example.com/t.vtt')),
            new AudioTranscript(new Track(), 'fr-CA', 'Français'),
        ]));

        self::assertSame([
            [
                'language' => 'en',
                'label' => 'English',
                'url' => 'https://e.example.com/t.vtt',
                'cues' => [
                    ['start' => '1000.000', 'end' => '1001.250', 'clock' => '16:40', 'datetime' => 'PT1000S', 'speaker' => null, 'text' => 'a'],
                    ['start' => '1002.000', 'end' => '1003.000', 'clock' => '16:42', 'datetime' => 'PT1002S', 'speaker' => null, 'text' => 'b'],
                ],
            ],
            ['language' => 'fr-CA', 'label' => 'Français', 'url' => null, 'cues' => []],
        ], $player->getTranscripts());
    }

    public function testBeforeAPurchaseOnlyTheTitlesTheListenerMayHearAreShown(): void
    {
        $player = new PlayerComponent();
        $player->mount(new Audio(
            'T',
            [],
            ListeningPolicy::purchaseOnly(),
            chapters: new ChapterList([new Chapter(0, 'First')]),
            transcripts: [new AudioTranscript(new Track([new Cue(0, 1_000, 'a')]), 'en', 'English', Url::fromString('https://e.example.com/t.vtt'))],
        ));

        self::assertSame([], $player->getChapters());
        self::assertSame([], $player->getTranscripts()[0]['cues']);
        self::assertNull($player->getTranscripts()[0]['url']);
    }
}
