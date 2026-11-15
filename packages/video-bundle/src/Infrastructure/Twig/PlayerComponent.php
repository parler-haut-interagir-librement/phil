<?php

declare(strict_types=1);

namespace Phil\VideoBundle\Infrastructure\Twig;

use LogicException;
use Phil\TimedText\Timecode;
use Phil\VideoBundle\Application\HostedVideo;
use Phil\VideoBundle\Application\LinkedVideo;
use Phil\VideoBundle\Application\TextTrack;
use Phil\VideoBundle\Application\Video;
use Phil\VideoBundle\Application\VideoSource;
use Phil\VideoBundle\Application\ViewingTracker;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The player of a video, `<twig:PhilVideo:Player :video="video" />`. A hosted video is the kit's recipe Video — the
 * browser's video element, its poster, its WebVTT tracks, nothing played before the viewer asks —, switched to its HLS
 * stream where the browser plays HLS. A linked video is a facade: the site's image and a button, the platform's player
 * loaded at the press, from its address without cookies, and a link to the platform without JavaScript. Both show the
 * chapters and the transcript beside the video. The viewing of a hosted video is counted only when the tracker says
 * so: tracking on and the viewer's consent.
 */
#[AsTwigComponent(name: 'PhilVideo:Player', template: '@PhilVideo/components/Player.html.twig', exposePublicProps: false)]
final class PlayerComponent
{
    /** The identifier of the player, from which the identifiers of its parts are made. */
    public string $id = 'phil-video';

    private ?Video $video = null;

    public function __construct(
        private readonly string $kitSubdirectory = '',
        private readonly ?ViewingTracker $tracker = null,
        private readonly string $endpoint = '',
    ) {
    }

    public function mount(Video $video): void
    {
        $this->video = $video;
    }

    /**
     * @throws LogicException when the player is drawn without a video
     */
    public function getVideo(): Video
    {
        return $this->video ?? throw new LogicException('The player is drawn with a video: give it "video".');
    }

    /**
     * The name of a recipe of the kit, as the application installed it.
     */
    public function kit(string $recipe): string
    {
        $path = trim($this->kitSubdirectory, '/');

        return ('' === $path ? '' : str_replace('/', ':', $path).':').$recipe;
    }

    public function isLinked(): bool
    {
        return $this->getVideo() instanceof LinkedVideo;
    }

    /**
     * Whether the player sends the viewing: a hosted video, tracking on, the viewer's consent, and an endpoint.
     */
    public function isTracking(): bool
    {
        return $this->getVideo() instanceof HostedVideo && '' !== $this->endpoint && true === $this->tracker?->isEnabled();
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * The sources of a hosted video, as the recipe takes them.
     *
     * @return list<array{src: string, type: string}>
     */
    public function getSources(): array
    {
        $video = $this->getVideo();

        return $video instanceof HostedVideo ? array_map(static fn (VideoSource $source): array => ['src' => $source->url->toString(), 'type' => $source->type], $video->sources) : [];
    }

    /**
     * The text tracks of a hosted video, as the recipe takes them.
     *
     * @return list<array{src: string, srclang: string, label: string, kind: string, default: bool}>
     */
    public function getTracks(): array
    {
        $video = $this->getVideo();

        return $video instanceof HostedVideo ? array_map(static fn (TextTrack $track): array => ['src' => $track->src->toString(), 'srclang' => $track->language, 'label' => $track->label, 'kind' => $track->kind->value, 'default' => $track->default], $video->tracks) : [];
    }

    /**
     * The address a link to an instant opens without JavaScript: the first file of a hosted video, with a media
     * fragment added; null for a linked video, whose instants are not links.
     */
    public function getHref(): ?string
    {
        $video = $this->getVideo();

        return $video instanceof HostedVideo ? $video->sources[0]->url->toString() : null;
    }

    /**
     * @return list<array{start: string, clock: string, datetime: string, title: string}>
     */
    public function getChapters(): array
    {
        $chapters = [];

        foreach ($this->getVideo()->chapters->chapters ?? [] as $chapter) {
            if ($chapter->toc) {
                $chapters[] = ['start' => $this->seconds($chapter->start), 'clock' => Timecode::formatClock($chapter->start), 'datetime' => Timecode::formatDuration($chapter->start), 'title' => $chapter->title];
            }
        }

        return $chapters;
    }

    /**
     * @return list<array{start: string, end: string, clock: string, datetime: string, speaker: ?string, text: string}>
     */
    public function getCues(): array
    {
        $cues = [];

        foreach ($this->getVideo()->transcript->cues ?? [] as $cue) {
            $cues[] = ['start' => $this->seconds($cue->start), 'end' => $this->seconds($cue->end), 'clock' => Timecode::formatClock($cue->start), 'datetime' => Timecode::formatDuration($cue->start), 'speaker' => $cue->speaker(), 'text' => $cue->plainText()];
        }

        return $cues;
    }

    private function seconds(int $milliseconds): string
    {
        return \sprintf('%d.%03d', intdiv($milliseconds, 1000), $milliseconds % 1000);
    }
}
