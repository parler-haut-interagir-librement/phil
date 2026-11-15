<?php

declare(strict_types=1);

namespace Phil\VideoBundle\Tests\Functional;

use Override;
use Phil\TimedText\Chapter;
use Phil\TimedText\ChapterList;
use Phil\TimedText\Cue;
use Phil\TimedText\Track;
use Phil\Url\Url;
use Phil\VideoBundle\Application\Contract\ViewingStore;
use Phil\VideoBundle\Application\HostedVideo;
use Phil\VideoBundle\Application\LinkedVideo;
use Phil\VideoBundle\Application\TextTrack;
use Phil\VideoBundle\Application\TextTrackKind;
use Phil\VideoBundle\Application\Video;
use Phil\VideoBundle\Application\VideoSource;
use Phil\VideoBundle\Infrastructure\Twig\PlayerComponent;
use Phil\VideoBundle\Tests\Functional\App\SwitchableConsent;
use Phil\VideoBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Environment;

/**
 * The player drawn by Twig in a booted kernel with the recipes Video, Image and Button of the PHIL UX kit, and the route
 * of the viewings answered.
 */
#[CoversClass(PlayerComponent::class)]
final class PlayerTest extends WebTestCase
{
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
    }

    private function draw(Video $video, string $locale = 'en'): Crawler
    {
        $switcher = self::getContainer()->get('translation.locale_switcher');
        self::assertInstanceOf(LocaleSwitcher::class, $switcher);
        $switcher->setLocale($locale);
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        return new Crawler($twig->createTemplate("{{ component('PhilVideo:Player', {video: video, id: 'v'}) }}")->render(['video' => $video]));
    }

    private function consent(bool $granted): void
    {
        $consent = self::getContainer()->get(SwitchableConsent::class);
        self::assertInstanceOf(SwitchableConsent::class, $consent);
        $consent->granted = $granted;
    }

    private function hosted(): HostedVideo
    {
        return new HostedVideo(
            'talk',
            'The talk',
            [new VideoSource(Url::fromString('https://cdn.example.com/talk.webm'), 'video/webm'), new VideoSource(Url::fromString('https://cdn.example.com/talk.mp4'))],
            640,
            360,
            Url::fromString('https://cdn.example.com/talk.jpg'),
            Url::fromString('https://cdn.example.com/talk.m3u8'),
            [new TextTrack(Url::fromString('https://cdn.example.com/talk.fr.vtt'), 'fr', 'Français', default: true), new TextTrack(Url::fromString('https://cdn.example.com/talk.chapters.vtt'), 'en', 'Chapters', TextTrackKind::Chapters)],
            new Track([new Cue(1_500, 3_000, '<v Ada>Welcome &amp; hello')]),
            'en',
            new ChapterList([new Chapter(0, 'Opening'), new Chapter(5_000, 'Hidden', toc: false)]),
        );
    }

    public function testAHostedVideoIsTheRecipeVideoWithItsTranscriptBeside(): void
    {
        self::bootKernel();
        $page = $this->draw($this->hosted());
        $c = 'phil--video-bundle--player';
        $root = $page->filter('div#v');

        self::assertSame('phil-video', $root->attr('class'));
        self::assertSame($c, $root->attr('data-controller'));
        self::assertSame('talk', $root->attr('data-'.$c.'-video-value'));
        self::assertSame('https://cdn.example.com/talk.m3u8', $root->attr('data-'.$c.'-hls-value'));
        self::assertSame('false', $root->attr('data-'.$c.'-tracking-value'));
        self::assertSame('', $root->attr('data-'.$c.'-endpoint-value'));

        $video = $page->filter('video#v-video');
        self::assertSame(['none', '640', '360', 'https://cdn.example.com/talk.jpg', 'The talk'], [$video->attr('preload'), $video->attr('width'), $video->attr('height'), $video->attr('poster'), $video->attr('aria-label')]);
        self::assertNull($video->attr('autoplay'));
        self::assertSame([['https://cdn.example.com/talk.webm', 'video/webm'], ['https://cdn.example.com/talk.mp4', 'video/mp4']], $video->filter('source')->each(static fn (Crawler $source): array => [$source->attr('src'), $source->attr('type')]));
        self::assertSame([['captions', 'fr', 'Français', 'default'], ['chapters', 'en', 'Chapters', null]], $video->filter('track')->each(static fn (Crawler $track): array => [$track->attr('kind'), $track->attr('srclang'), $track->attr('label'), null === $track->attr('default') ? null : 'default']));
        self::assertSame('Download the video', $video->filter('a')->text());

        self::assertSame([['0.000', 'Opening', 'https://cdn.example.com/talk.webm#t=0.000']], $page->filter('nav.phil-video__chapters li')->each(static fn (Crawler $item): array => [(string) $item->attr('data-start'), $item->filter('a')->text(), (string) $item->filter('a')->attr('href')]));
        self::assertSame('Chapters', $page->filter('nav h3#v-chapters')->text());

        $transcript = $page->filter('details.phil-video__transcript');
        self::assertSame('en', $transcript->attr('lang'));
        self::assertSame('Transcript', $transcript->filter('summary')->text());
        self::assertSame('0:01 Ada Welcome & hello', $transcript->filter('li')->text());
        self::assertSame(['1.500', '3.000'], [$transcript->filter('li')->attr('data-start'), $transcript->filter('li')->attr('data-end')]);
        self::assertSame($c.'#seek', $transcript->filter('a')->attr('data-action'));
        self::assertSame('1.500', $transcript->filter('a')->attr('data-'.$c.'-time-param'));
    }

    public function testTheViewingIsSentOnlyWithConsent(): void
    {
        self::bootKernel();
        $this->consent(true);
        $root = $this->draw($this->hosted())->filter('div#v');

        self::assertSame('true', $root->attr('data-phil--video-bundle--player-tracking-value'));
        self::assertSame('/_phil/video/progress', $root->attr('data-phil--video-bundle--player-endpoint-value'));
    }

    public function testALinkedVideoIsAFacadeThatFetchesNothingOfThePlatform(): void
    {
        self::bootKernel();
        $this->consent(true);
        $page = $this->draw(LinkedVideo::fromUrl('yt', 'A linked talk', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', Url::fromString('https://cdn.example.com/yt.jpg')), 'fr');
        $c = 'phil--video-bundle--player';
        $figure = $page->filter('figure#v');

        self::assertSame('phil-video phil-video--linked', $figure->attr('class'));
        self::assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1', $figure->attr('data-'.$c.'-embed-value'));
        self::assertSame('A linked talk', $figure->attr('data-'.$c.'-title-value'));
        self::assertSame(['1280', '720'], [$figure->attr('data-'.$c.'-width-value'), $figure->attr('data-'.$c.'-height-value')]);
        self::assertCount(0, $page->filter('iframe'));
        self::assertCount(0, $page->filter('[data-'.$c.'-tracking-value]'));
        self::assertSame(['https://cdn.example.com/yt.jpg', '', 'lazy'], [$page->filter('img')->attr('src'), $page->filter('img')->attr('alt'), $page->filter('img')->attr('loading')]);

        $button = $page->filter('button');
        self::assertNotNull($button->attr('hidden'));
        self::assertSame('button', $button->attr('type'));
        self::assertSame($c.'#load', $button->attr('data-action'));
        self::assertSame('play', $button->attr('data-'.$c.'-target'));
        self::assertSame('Lire la vidéo : A linked talk', $button->text());
        self::assertSame('La lecture charge le lecteur de YouTube, qui reçoit votre adresse IP.', $page->filter('.phil-video__notice')->text());
        self::assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $page->filter('figcaption a')->attr('href'));
        self::assertSame('Voir sur YouTube', $page->filter('figcaption a')->text());
        self::assertSame('noopener noreferrer', $page->filter('figcaption a')->attr('rel'));
        self::assertCount(0, $page->filter('img')->reduce(static fn (Crawler $image): bool => str_contains((string) $image->attr('src'), 'youtube')));
    }

    public function testTheChaptersAndTranscriptOfALinkedVideoAreText(): void
    {
        self::bootKernel();
        $linked = new LinkedVideo(
            'yt',
            'Talk',
            LinkedVideo::fromUrl('yt', 'Talk', 'https://vimeo.com/76979871')->player,
            Url::fromString('https://vimeo.com/76979871'),
            transcript: new Track([new Cue(0, 1_000, 'Hi')]),
            chapters: new ChapterList([new Chapter(61_000, 'Part')]),
        );
        $page = $this->draw($linked);

        self::assertSame('1:01 Part', preg_replace('/\s+/', ' ', trim($page->filter('nav li')->text())));
        self::assertCount(0, $page->filter('nav a'));
        self::assertSame('0:00 Hi', $page->filter('details li')->text());
        self::assertCount(0, $page->filter('details a'));
        self::assertNull($page->filter('details')->attr('lang'));
        self::assertCount(0, $page->filter('img'));
        self::assertSame('Watch on Vimeo', $page->filter('figcaption a')->text());
    }

    public function testTheRouteCountsAViewingWithConsent(): void
    {
        $client = self::createClient();
        $this->consent(true);
        $client->request('POST', '/_phil/video/progress', server: ['HTTP_SEC_FETCH_SITE' => 'same-origin'], content: '{"video":"talk","parts":"'.str_repeat('1', 50).str_repeat('0', 50).'"}');

        self::assertResponseStatusCodeSame(204);
        $store = self::getContainer()->get(ViewingStore::class);
        self::assertInstanceOf(ViewingStore::class, $store);
        self::assertSame(1, $store->heatmap('talk')->viewings);

        $client->request('GET', '/_phil/video/progress');
        self::assertResponseStatusCodeSame(405);
    }
}
