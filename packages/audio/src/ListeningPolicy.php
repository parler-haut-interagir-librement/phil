<?php

declare(strict_types=1);

namespace Phil\Audio;

use Phil\Audio\Exception\InvalidAudio;

/**
 * The creator's choice of what a listener who has not bought a track may hear: the whole track, an excerpt, or nothing
 * before the purchase. A listener entitled to the track — who bought it, or whom the application lets in — hears it
 * whole.
 */
final readonly class ListeningPolicy
{
    /**
     * @throws InvalidAudio when the mode is an excerpt without one, or another mode with one
     */
    public function __construct(
        public ListeningMode $mode = ListeningMode::Full,
        public ?Excerpt $excerpt = null,
    ) {
        if ((ListeningMode::Excerpt === $mode) !== ($excerpt instanceof Excerpt)) {
            throw new InvalidAudio('An excerpt is given with the excerpt mode, and only with it.');
        }
    }

    public static function full(): self
    {
        return new self(ListeningMode::Full);
    }

    public static function excerpt(Excerpt $excerpt): self
    {
        return new self(ListeningMode::Excerpt, $excerpt);
    }

    public static function purchaseOnly(): self
    {
        return new self(ListeningMode::PurchaseOnly);
    }

    /**
     * What this listener may hear.
     */
    public function forListener(bool $entitled): ListeningMode
    {
        return $entitled ? ListeningMode::Full : $this->mode;
    }
}
