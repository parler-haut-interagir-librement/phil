<?php

declare(strict_types=1);

namespace Phil\AudioBundle\Tests\Functional;

use Override;
use Phil\Audio\Audio;
use Phil\Audio\AudioFormat;
use Phil\Audio\AudioSource;
use Phil\Audio\AudioTranscript;
use Phil\Audio\Excerpt;
use Phil\Audio\ListeningPolicy;
use Phil\Audio\Peaks;
use Phil\AudioBundle\Application\AudioJobs;
use Phil\AudioBundle\Infrastructure\Twig\PlayerComponent;
use Phil\AudioBundle\Tests\Functional\App\TestKernel;
use Phil\TimedText\Chapter;
use Phil\TimedText\ChapterList;
use Phil\TimedText\Cue;
use Phil\TimedText\Track;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Environment;

/**
 * The player drawn by Twig in a booted kernel, with the recipe Button of the PHIL UX kit, in English and in French.
 */
#[CoversClass(PlayerComponent::class)]
final class PlayerTest extends KernelTestCase
{
    private Environment $twig;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);
        $this->twig = $twig;
    }

    private function track(ListeningPolicy $policy = new ListeningPolicy()): Audio
    {
        return new Audio(
            'Episode 12: Dice & dragons',
            [
                new AudioSource(Url::fromString('https://cdn.example.com/e12.opus'), AudioFormat::Opus),
                new AudioSource(Url::fromString('https://cdn.example.com/e12.mp3'), AudioFormat::Mp3),
                new AudioSource(Url::fromString('https://cdn.example.com/e12-excerpt.mp3'), AudioFormat::Mp3, excerpt: true),
            ],
            $policy,
            3_725_000,
            new Peaks(8_000, 800, 8, [-128, 127, -64, 32, 0, 0]),
            new ChapterList([new Chapter(0, 'Welcome'), new Chapter(65_000, 'Hidden', toc: false), new Chapter(3_725_000 - 1_000, 'Credits')]),
            [new AudioTranscript(new Track([new Cue(0, 2_000, '<v Ada>Hello &amp; welcome'), new Cue(40_000, 45_000, 'Later')]), 'en', 'English', Url::fromString('https://cdn.example.com/e12.vtt'))],
        );
    }

    private function draw(Audio $audio, bool $entitled = false, string $locale = 'en'): Crawler
    {
        $switcher = self::getContainer()->get('translation.locale_switcher');
        self::assertInstanceOf(LocaleSwitcher::class, $switcher);
        $switcher->setLocale($locale);

        return new Crawler($this->twig->createTemplate("{{ component('PhilAudio:Player', {audio: audio, entitled: entitled, id: 'e12', class: 'wide'}) }}")->render(['audio' => $audio, 'entitled' => $entitled]));
    }

    public function testTheWholeTrackIsAnAudioElementWithItsChaptersAndTranscript(): void
    {
        $page = $this->draw($this->track());
        $figure = $page->filter('figure#e12');
        $c = 'phil--audio-bundle--player';

        self::assertSame('phil-audio wide', $figure->attr('class'));
        self::assertSame($c, $figure->attr('data-controller'));
        self::assertSame('[1,0.5,0]', $figure->attr('data-'.$c.'-peaks-value'));
        self::assertSame('full', $figure->attr('data-phil-audio-mode'));
        self::assertSame('Episode 12: Dice & dragons', $page->filter('figcaption#e12-title')->text());
        self::assertCount(0, $page->filter('.phil-audio__excerpt'));

        $audio = $page->filter('audio');
        self::assertSame('e12-title', $audio->attr('aria-labelledby'));
        self::assertSame('metadata', $audio->attr('preload'));
        self::assertNotNull($audio->attr('controls'));
        self::assertNull($audio->attr('autoplay'));
        self::assertSame([['https://cdn.example.com/e12.opus', 'audio/ogg; codecs=opus'], ['https://cdn.example.com/e12.mp3', 'audio/mpeg']], $audio->filter('source')->each(static fn (Crawler $source): array => [$source->attr('src'), $source->attr('type')]));
        self::assertSame('https://cdn.example.com/e12.opus', $audio->filter('a')->attr('href'));
        self::assertSame('Your browser does not play this audio: download it.', $audio->filter('a')->text());

        $waveform = $page->filter('svg.phil-audio__waveform');
        self::assertSame('true', $waveform->attr('aria-hidden'));
        self::assertSame('0 0 3 100', $waveform->attr('viewBox'));
        self::assertSame('click->'.$c.'#scrub', $waveform->attr('data-action'));

        $chapters = $page->filter('nav.phil-audio__chapters');
        self::assertSame('e12-chapters', $chapters->attr('aria-labelledby'));
        self::assertSame('Chapters', $chapters->filter('h3#e12-chapters')->text());
        self::assertSame([
            ['0.000', 'PT0S', '0:00', 'Welcome', 'https://cdn.example.com/e12.opus#t=0.000', '0.000'],
            ['3724.000', 'PT3724S', '1:02:04', 'Credits', 'https://cdn.example.com/e12.opus#t=3724.000', '3724.000'],
        ], $chapters->filter('li')->each(static fn (Crawler $item): array => [
            $item->attr('data-start'),
            $item->filter('time')->attr('datetime'),
            $item->filter('time')->text(),
            $item->filter('a')->text(),
            $item->filter('a')->attr('href'),
            $item->filter('a')->attr('data-'.$c.'-time-param'),
        ]));
        self::assertSame($c.'#seek', $chapters->filter('a')->attr('data-action'));
        self::assertSame('link', $chapters->filter('a')->attr('data-variant'));

        $transcript = $page->filter('details.phil-audio__transcript');
        self::assertSame('en', $transcript->attr('lang'));
        self::assertSame('Transcript (English)', $transcript->filter('summary')->text());
        self::assertSame(['0.000', '2.000'], [$transcript->filter('li')->first()->attr('data-start'), $transcript->filter('li')->first()->attr('data-end')]);
        self::assertSame('0:00 Ada Hello & welcome', $transcript->filter('li')->first()->text());
        self::assertSame('Ada', $transcript->filter('li strong')->text());
        self::assertSame('https://cdn.example.com/e12.vtt', $transcript->filter('a[download]')->attr('href'));
        self::assertSame('Download the transcript (English)', $transcript->filter('a[download]')->text());
    }

    public function testAnExcerptPlaysItsOwnFileWithItsOwnInstants(): void
    {
        $page = $this->draw($this->track(ListeningPolicy::excerpt(new Excerpt(30_000, 30_000))), locale: 'fr');

        self::assertSame('excerpt', $page->filter('figure')->attr('data-phil-audio-mode'));
        self::assertSame('(extrait)', $page->filter('.phil-audio__excerpt')->text());
        self::assertSame(['https://cdn.example.com/e12-excerpt.mp3'], $page->filter('source')->each(static fn (Crawler $source): ?string => $source->attr('src')));
        self::assertCount(0, $page->filter('nav'));
        self::assertSame([['10.000', 'https://cdn.example.com/e12-excerpt.mp3#t=10.000']], $page->filter('details li')->each(static fn (Crawler $item): array => [$item->attr('data-start'), (string) $item->filter('a')->attr('href')]));
        self::assertSame('Transcription (English)', $page->filter('summary')->text());
        self::assertCount(0, $page->filter('a[download]'));
    }

    public function testNothingPlaysBeforeAPurchaseWhenTheCreatorSaysSo(): void
    {
        $page = $this->draw($this->track(ListeningPolicy::purchaseOnly()));

        self::assertCount(0, $page->filter('audio'));
        self::assertCount(0, $page->filter('svg'));
        self::assertCount(0, $page->filter('nav'));
        self::assertCount(0, $page->filter('details'));
        self::assertSame('Buy the track to hear it.', $page->filter('p.phil-audio__locked[role="note"]')->text());

        $bought = $this->draw($this->track(ListeningPolicy::purchaseOnly()), entitled: true);
        self::assertCount(2, $bought->filter('source'));
        self::assertCount(1, $bought->filter('a[download]'));
    }

    public function testATrackWithoutWaveformChaptersOrTranscriptIsTheAudioAlone(): void
    {
        $page = $this->draw(new Audio('Bare', [new AudioSource(Url::fromString('https://cdn.example.com/b.mp3'), AudioFormat::Mp3)], transcripts: [new AudioTranscript(new Track(), 'fr', 'Français')]));

        self::assertSame('[]', $page->filter('figure')->attr('data-phil--audio-bundle--player-peaks-value'));
        self::assertCount(1, $page->filter('audio'));
        self::assertCount(0, $page->filter('svg'));
        self::assertCount(0, $page->filter('nav'));
        self::assertCount(0, $page->filter('details'));
    }

    public function testTheJobsReachTheBusOfTheApplication(): void
    {
        self::assertInstanceOf(AudioJobs::class, self::getContainer()->get(AudioJobs::class));
    }
}
