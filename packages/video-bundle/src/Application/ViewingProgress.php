<?php

declare(strict_types=1);

namespace Phil\VideoBundle\Application;

use Phil\VideoBundle\Application\Exception\InvalidVideo;

/**
 * What one viewer watched of a video, in a hundred equal parts, each watched or not: the share watched, and the parts,
 * which add to the heat map of the video. Nothing says who the viewer is.
 */
final readonly class ViewingProgress
{
    public const int PARTS = 100;

    /**
     * @param list<int> $parts the parts watched, from 0 to 99, each once, in order
     */
    private function __construct(
        public string $videoId,
        public array $parts,
    ) {
    }

    /**
     * The progress a player sends: a hundred characters, `1` for a part watched and `0` for another.
     *
     * @throws InvalidVideo when the identifier or the parts are not that
     */
    public static function fromString(string $videoId, string $parts): self
    {
        if (1 !== preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $videoId)) {
            throw new InvalidVideo('The identifier of a video holds letters, digits, "_" and "-", 64 at most.');
        }

        if (1 !== preg_match('/^[01]{100}$/D', $parts)) {
            throw new InvalidVideo('A viewing is a hundred parts, each 0 or 1.');
        }

        return new self($videoId, array_keys(array_filter(str_split($parts), static fn (string $part): bool => '1' === $part)));
    }

    /**
     * The share of the video watched, in percent.
     */
    public function percent(): int
    {
        return \count($this->parts);
    }

    public function isComplete(): bool
    {
        return self::PARTS === $this->percent();
    }
}
