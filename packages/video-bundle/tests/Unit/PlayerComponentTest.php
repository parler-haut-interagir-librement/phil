<?php

declare(strict_types=1);

namespace Phil\VideoBundle\Tests\Unit;

use LogicException;
use Phil\TimedText\Chapter;
use Phil\TimedText\ChapterList;
use Phil\TimedText\Cue;
use Phil\TimedText\Track;
use Phil\Url\Url;
use Phil\VideoBundle\Application\Contract\ViewingConsent;
use Phil\VideoBundle\Application\HostedVideo;
use Phil\VideoBundle\Application\LinkedVideo;
use Phil\VideoBundle\Application\TextTrack;
use Phil\VideoBundle\Application\TextTrackKind;
use Phil\VideoBundle\Application\VideoSource;
use Phil\VideoBundle\Application\ViewingTracker;
use Phil\VideoBundle\Infrastructure\Memory\InMemoryViewingStore;
use Phil\VideoBundle\Infrastructure\Twig\PlayerComponent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversClass(PlayerComponent::class)]
final class PlayerComponentTest extends TestCase
{
    private function tracker(bool $granted): ViewingTracker
    {
        $consent = new readonly class($granted) implements ViewingConsent {
            public function __construct(private bool $granted)
            {
            }

            public function granted(): bool
            {
                return $this->granted;
            }
        };

        return new ViewingTracker($consent, new InMemoryViewingStore(), new EventDispatcher());
    }

    public function testTheRecipesAreNamedUnderTheSubdirectoryOfTheKit(): void
    {
        self::assertSame('Video', new PlayerComponent()->kit('Video'));
        self::assertSame('ui:Video', new PlayerComponent('/ui/')->kit('Video'));
        self::assertSame('a:b:Video', new PlayerComponent('a/b')->kit('Video'));
        self::assertSame('Video', new PlayerComponent('/')->kit('Video'));
    }

    public function testAPlayerIsDrawnWithAVideo(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('The player is drawn with a video: give it "video".');

        new PlayerComponent()->getVideo();
    }

    public function testOnlyAHostedVideoWithConsentAndAnEndpointIsTracked(): void
    {
        $hosted = new HostedVideo('a', 'A', [new VideoSource(Url::fromString('https://cdn.example.com/a.mp4'))]);
        $linked = LinkedVideo::fromUrl('b', 'B', 'https://vimeo.com/1');

        $player = new PlayerComponent('', $this->tracker(true), '/p');
        $player->mount($hosted);
        self::assertTrue($player->isTracking());
        self::assertSame('/p', $player->getEndpoint());
        self::assertSame('phil-video', $player->id);
        self::assertFalse($player->isLinked());

        $player->mount($linked);
        self::assertFalse($player->isTracking());
        self::assertTrue($player->isLinked());
        self::assertSame([], $player->getSources());
        self::assertSame([], $player->getTracks());
        self::assertNull($player->getHref());
        self::assertSame([], $player->getChapters());
        self::assertSame([], $player->getCues());

        foreach ([new PlayerComponent('', $this->tracker(false), '/p'), new PlayerComponent('', $this->tracker(true)), new PlayerComponent('', null, '/p'), new PlayerComponent()] as $other) {
            $other->mount($hosted);
            self::assertFalse($other->isTracking());
        }
    }

    public function testAHostedVideoGivesItsFilesItsTracksAndItsFirstAddress(): void
    {
        $player = new PlayerComponent();
        $player->mount(new HostedVideo(
            'a',
            'A',
            [new VideoSource(Url::fromString('https://cdn.example.com/a.webm'), 'video/webm'), new VideoSource(Url::fromString('https://cdn.example.com/a.mp4'))],
            tracks: [
                new TextTrack(Url::fromString('https://cdn.example.com/a.fr.vtt'), 'fr-CA', 'Français', default: true),
                new TextTrack(Url::fromString('https://cdn.example.com/a.en.vtt'), 'en', 'English', TextTrackKind::Subtitles),
            ],
        ));

        self::assertSame([['src' => 'https://cdn.example.com/a.webm', 'type' => 'video/webm'], ['src' => 'https://cdn.example.com/a.mp4', 'type' => 'video/mp4']], $player->getSources());
        self::assertSame([
            ['src' => 'https://cdn.example.com/a.fr.vtt', 'srclang' => 'fr-CA', 'label' => 'Français', 'kind' => 'captions', 'default' => true],
            ['src' => 'https://cdn.example.com/a.en.vtt', 'srclang' => 'en', 'label' => 'English', 'kind' => 'subtitles', 'default' => false],
        ], $player->getTracks());
        self::assertSame('https://cdn.example.com/a.webm', $player->getHref());
    }

    public function testTheChaptersOfTheTableOfContentsAndEveryCueAreListed(): void
    {
        $player = new PlayerComponent();
        $player->mount(new HostedVideo(
            'a',
            'A',
            [new VideoSource(Url::fromString('https://cdn.example.com/a.mp4'))],
            transcript: new Track([new Cue(1_000_000, 1_001_250, 'a'), new Cue(1_002_000, 1_003_000, 'b')]),
            chapters: new ChapterList([new Chapter(1_000_000, 'First'), new Chapter(1_001_000, 'Hidden', toc: false), new Chapter(1_002_000, 'Second')]),
        ));

        self::assertSame([
            ['start' => '1000.000', 'clock' => '16:40', 'datetime' => 'PT1000S', 'title' => 'First'],
            ['start' => '1002.000', 'clock' => '16:42', 'datetime' => 'PT1002S', 'title' => 'Second'],
        ], $player->getChapters());
        self::assertSame([
            ['start' => '1000.000', 'end' => '1001.250', 'clock' => '16:40', 'datetime' => 'PT1000S', 'speaker' => null, 'text' => 'a'],
            ['start' => '1002.000', 'end' => '1003.000', 'clock' => '16:42', 'datetime' => 'PT1002S', 'speaker' => null, 'text' => 'b'],
        ], $player->getCues());
    }
}
