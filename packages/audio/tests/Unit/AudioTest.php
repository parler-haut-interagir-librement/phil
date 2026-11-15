<?php

declare(strict_types=1);

namespace Phil\Audio\Tests\Unit;

use Phil\Audio\Audio;
use Phil\Audio\AudioFormat;
use Phil\Audio\AudioSource;
use Phil\Audio\AudioTranscript;
use Phil\Audio\Exception\InvalidAudio;
use Phil\Audio\Excerpt;
use Phil\Audio\ListeningMode;
use Phil\Audio\ListeningPolicy;
use Phil\TimedText\Chapter;
use Phil\TimedText\ChapterList;
use Phil\TimedText\Cue;
use Phil\TimedText\Track;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Audio::class)]
#[CoversClass(AudioSource::class)]
#[CoversClass(AudioTranscript::class)]
final class AudioTest extends TestCase
{
    private AudioSource $whole;

    private AudioSource $wholeOpus;

    private AudioSource $excerpt;

    protected function setUp(): void
    {
        $this->whole = new AudioSource(Url::fromString('https://cdn.example.com/track.mp3'), AudioFormat::Mp3);
        $this->wholeOpus = new AudioSource(Url::fromString('https://cdn.example.com/track.opus'), AudioFormat::Opus);
        $this->excerpt = new AudioSource(Url::fromString('https://cdn.example.com/track-excerpt.mp3'), AudioFormat::Mp3, excerpt: true);
    }

    public function testATrackCarriesItsChaptersAndTranscripts(): void
    {
        $chapters = new ChapterList([new Chapter(0, 'Opening')]);
        $transcript = new AudioTranscript(new Track([new Cue(0, 1_000, 'Hello')]), 'fr-CA', 'Français');
        $audio = new Audio('Track', [$this->whole], duration: 0, chapters: $chapters, transcripts: [$transcript]);

        self::assertSame(ListeningMode::Full, $audio->policy->mode);
        self::assertSame($chapters, $audio->chapters);
        self::assertSame([$transcript], $audio->transcripts);
        self::assertSame('audio/mpeg', $this->whole->mediaType());
        self::assertFalse($this->whole->excerpt);
        self::assertNull($transcript->url);
        self::assertNull($audio->peaks);
    }

    public function testTheWholeFilesArePlayedUnderAFullPolicy(): void
    {
        $audio = new Audio('Track', [$this->excerpt, $this->whole, $this->wholeOpus]);

        self::assertSame([$this->whole, $this->wholeOpus], $audio->playable(false));
        self::assertSame(ListeningMode::Full, $audio->mode(false));
    }

    public function testOnlyTheExcerptIsPlayedBeforeAPurchase(): void
    {
        $audio = new Audio('Track', [$this->whole, $this->excerpt], ListeningPolicy::excerpt(new Excerpt(0, 30_000)));

        self::assertSame([$this->excerpt], $audio->playable(false));
        self::assertSame([$this->whole], $audio->playable(true));
        self::assertSame(ListeningMode::Excerpt, $audio->mode(false));
    }

    public function testNothingIsPlayedBeforeAPurchaseWhenTheCreatorSaysSo(): void
    {
        $audio = new Audio('Track', [$this->whole, $this->excerpt], ListeningPolicy::purchaseOnly());

        self::assertSame([], $audio->playable(false));
        self::assertSame([$this->whole], $audio->playable(true));
    }

    public function testATrackHasATitleAndNoNegativeDuration(): void
    {
        try {
            new Audio(' ', []);
            self::fail('A blank title is refused.');
        } catch (InvalidAudio $invalidAudio) {
            self::assertSame('A track has a title.', $invalidAudio->getMessage());
        }

        $this->expectException(InvalidAudio::class);
        $this->expectExceptionMessageIsOrContains('A duration is not negative.');
        new Audio('Track', [], duration: -1);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badTranscripts(): iterable
    {
        yield 'no language' => ['', 'Label'];
        yield 'not a tag' => ['french', 'Label'];
        yield 'a tag then text' => ["fr\nx", 'Label'];
        yield 'blank label' => ['fr', ' '];
    }

    #[DataProvider('badTranscripts')]
    public function testATranscriptHasALanguageTagAndALabel(string $language, string $label): void
    {
        $this->expectException(InvalidAudio::class);

        new AudioTranscript(new Track(), $language, $label);
    }

    public function testATranscriptMayBeOfferedForDownload(): void
    {
        $url = Url::fromString('https://example.com/t.vtt');
        $transcript = new AudioTranscript(new Track(), 'en', 'English', $url);

        self::assertSame($url, $transcript->url);
        self::assertSame('en', $transcript->language);
        self::assertSame('English', $transcript->label);
    }
}
