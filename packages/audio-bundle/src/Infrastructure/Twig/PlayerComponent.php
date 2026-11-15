<?php

declare(strict_types=1);

namespace Phil\AudioBundle\Infrastructure\Twig;

use LogicException;
use Phil\Audio\Audio;
use Phil\Audio\AudioSource;
use Phil\Audio\Excerpt;
use Phil\Audio\ListeningMode;
use Phil\TimedText\Timecode;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The player of a track, `<twig:PhilAudio:Player :audio="track" :entitled="bought" />`: the browser's `<audio>`, which
 * plays without JavaScript and answers the keyboard and screen readers, the files this listener may hear, a waveform
 * the Stimulus controller draws, and the chapters and transcripts, which seek the audio once the controller runs and
 * link to the instant in the file without it.
 */
#[AsTwigComponent(name: 'PhilAudio:Player', template: '@PhilAudio/components/Player.html.twig', exposePublicProps: false)]
final class PlayerComponent
{
    /** Whether this listener bought the track, or may hear it whole for another reason. */
    public bool $entitled = false;

    /** The bars of the waveform, one at least. */
    public int $bars = 120;

    /** The identifier of the player, from which the identifiers of its parts are made. */
    public string $id = 'phil-audio';

    private ?Audio $audio = null;

    public function __construct(
        private readonly string $kitSubdirectory = '',
    ) {
    }

    public function mount(Audio $audio): void
    {
        $this->audio = $audio;
    }

    /**
     * @throws LogicException when the player is drawn without a track
     */
    public function getAudio(): Audio
    {
        return $this->audio ?? throw new LogicException('The player is drawn with a track: give it "audio".');
    }

    /**
     * The name of a recipe of the kit, as the application installed it.
     */
    public function kit(string $recipe): string
    {
        $path = trim($this->kitSubdirectory, '/');

        return ('' === $path ? '' : str_replace('/', ':', $path).':').$recipe;
    }

    public function getMode(): string
    {
        return $this->getAudio()->mode($this->entitled)->value;
    }

    public function isExcerpt(): bool
    {
        return ListeningMode::Excerpt === $this->getAudio()->mode($this->entitled);
    }

    /**
     * @return list<AudioSource>
     */
    public function getSources(): array
    {
        return $this->getAudio()->playable($this->entitled);
    }

    /**
     * @return list<float>
     */
    public function getAmplitudes(): array
    {
        return $this->getAudio()->peaks?->amplitudes(max(1, $this->bars)) ?? [];
    }

    /**
     * The chapters shown in the table of contents that this listener may hear: those the excerpt reaches when only it is
     * heard, none before a purchase.
     *
     * @return list<array{start: string, clock: string, datetime: string, title: string}>
     */
    public function getChapters(): array
    {
        $chapters = [];

        foreach ($this->getAudio()->chapters->chapters ?? [] as $chapter) {
            if ($chapter->toc && $this->reaches($chapter->start)) {
                $chapters[] = ['start' => $this->seconds($chapter->start), 'clock' => Timecode::formatClock($chapter->start), 'datetime' => Timecode::formatDuration($chapter->start), 'title' => $chapter->title];
            }
        }

        return $chapters;
    }

    /**
     * The transcripts, each with the cues this listener may hear, and the address of its file only for a listener who
     * may hear the whole track.
     *
     * @return list<array{language: string, label: string, url: ?string, cues: list<array{start: string, end: string, clock: string, datetime: string, speaker: ?string, text: string}>}>
     */
    public function getTranscripts(): array
    {
        $transcripts = [];

        foreach ($this->getAudio()->transcripts as $transcript) {
            $cues = [];

            foreach ($transcript->track->cues as $cue) {
                if ($this->reaches($cue->start)) {
                    $cues[] = ['start' => $this->seconds($cue->start), 'end' => $this->seconds($cue->end), 'clock' => Timecode::formatClock($cue->start), 'datetime' => Timecode::formatDuration($cue->start), 'speaker' => $cue->speaker(), 'text' => $cue->plainText()];
                }
            }

            $transcripts[] = ['language' => $transcript->language, 'label' => $transcript->label, 'url' => ListeningMode::Full === $this->getAudio()->mode($this->entitled) ? $transcript->url?->toString() : null, 'cues' => $cues];
        }

        return $transcripts;
    }

    /**
     * Whether this listener may hear the instant: any instant of the whole track, those of the excerpt when only it is
     * heard, none before a purchase.
     */
    private function reaches(int $instant): bool
    {
        $excerpt = $this->getAudio()->policy->excerpt;

        return match ($this->getAudio()->mode($this->entitled)) {
            ListeningMode::Full => true,
            ListeningMode::Excerpt => $excerpt instanceof Excerpt && $instant >= $excerpt->start && $instant < $excerpt->end(),
            ListeningMode::PurchaseOnly => false,
        };
    }

    /**
     * An instant of the track as an instant of the file played, in seconds: the file of an excerpt starts at the
     * excerpt's start.
     */
    private function seconds(int $instant): string
    {
        $milliseconds = $instant - ($this->isExcerpt() ? $this->getAudio()->policy->excerpt->start ?? 0 : 0);

        return \sprintf('%d.%03d', intdiv($milliseconds, 1000), $milliseconds % 1000);
    }
}
