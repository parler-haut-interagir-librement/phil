<?php

declare(strict_types=1);

namespace Phil\AudioBundle\Tests\Support;

use Closure;
use Override;
use Phil\Audio\Contract\PeaksGenerator;
use Phil\Audio\Contract\Transcoder;
use Phil\Audio\Excerpt;
use Phil\Audio\Peaks;
use Phil\Audio\Rendition;

/**
 * The audio tools replaced: a transcoder that writes its source in capitals, and a waveform of one run, unless closures
 * say otherwise.
 */
final class MemoryTools implements Transcoder, PeaksGenerator
{
    /** @var list<array{source: string, target: string, rendition: Rendition, excerpt: ?Excerpt}> */
    public array $transcoded = [];

    /**
     * @param (Closure(string, string, Rendition, ?Excerpt): void)|null $transcode
     * @param (Closure(string, int): Peaks)|null                        $peaks
     */
    public function __construct(
        public ?Closure $transcode = null,
        public ?Closure $peaks = null,
    ) {
    }

    #[Override]
    public function transcode(string $source, string $target, Rendition $rendition, ?Excerpt $excerpt = null): void
    {
        $this->transcoded[] = ['source' => $source, 'target' => $target, 'rendition' => $rendition, 'excerpt' => $excerpt];

        if ($this->transcode instanceof Closure) {
            ($this->transcode)($source, $target, $rendition, $excerpt);

            return;
        }

        file_put_contents($target, strtoupper((string) file_get_contents($source)));
    }

    #[Override]
    public function peaks(string $path, int $samplesPerPixel = 800): Peaks
    {
        return $this->peaks instanceof Closure ? ($this->peaks)($path, $samplesPerPixel) : new Peaks(8_000, $samplesPerPixel, 8, [0, 1]);
    }
}
