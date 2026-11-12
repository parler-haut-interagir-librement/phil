<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Application;

use Phil\SocialCards\Card\CardContent;
use Phil\SocialCards\Card\CardGenerator;
use Phil\SocialCards\Card\Fingerprint;
use Phil\SocialCards\Contract\CardStore;
use Phil\SocialCardsBundle\Application\Contract\CardQueue;
use Phil\SocialCardsBundle\Application\Contract\CardRequestStore;

/**
 * The cards of the pages: a page names its card by its content and gets a fingerprint, its address; the card is drawn
 * once, by a worker when a queue is there — the message carries the fingerprint alone —, else by the first request for
 * the image.
 */
final readonly class SocialCards
{
    public function __construct(
        private CardTemplates $templates,
        private CardGenerator $generator,
        private CardRequestStore $requests,
        private CardStore $cards,
        private ?CardQueue $queue = null,
    ) {
    }

    /**
     * The fingerprint of the page's card, its request kept and, the first time, queued to be drawn.
     */
    public function request(CardContent $content, ?string $template = null): string
    {
        $template ??= $this->templates->default();
        $fingerprint = $this->generator->fingerprint($this->templates->get($template), $content);

        if ($this->requests->save($fingerprint, new CardRequest($template, $content))) {
            $this->queue?->queue($fingerprint);
        }

        return $fingerprint;
    }

    /**
     * The PNG of a card: kept, or drawn now from its request; null for a fingerprint no page named, or whose template
     * the configuration no longer declares.
     */
    public function image(string $fingerprint): ?string
    {
        if (!Fingerprint::isValid($fingerprint)) {
            return null;
        }

        $png = $this->cards->get($fingerprint);

        if (null !== $png) {
            return $png;
        }

        $request = $this->requests->find($fingerprint);

        if (!$request instanceof CardRequest || !$this->templates->has($request->template)) {
            return null;
        }

        return $this->generator->generate($this->templates->get($request->template), $request->content)->png;
    }

    /**
     * Draws a card ahead of its first request; whether there is a card for that fingerprint.
     */
    public function generate(string $fingerprint): bool
    {
        return null !== $this->image($fingerprint);
    }
}
