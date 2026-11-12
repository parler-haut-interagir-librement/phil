<?php

declare(strict_types=1);

namespace Phil\SocialCards\Card;

use Phil\SocialCards\Contract\CardRenderer;
use Phil\SocialCards\Contract\CardStore;

/**
 * Draws each card once: its fingerprint hashes the renderer's version, the template's fingerprint and the content, and
 * a card the store keeps under it is not drawn again. A change of title, of template or of renderer gives a new
 * fingerprint, hence a new address that no cache holds yet.
 */
final readonly class CardGenerator
{
    public function __construct(
        private CardRenderer $renderer,
        private CardStore $store,
    ) {
    }

    /**
     * 32 lower-case hexadecimal digits.
     */
    public function fingerprint(CardTemplate $template, CardContent $content): string
    {
        return hash('xxh128', implode("\0", [$this->renderer->version(), $template->fingerprint(), $content->title, $content->subtitle ?? '', $content->locale]));
    }

    public function generate(CardTemplate $template, CardContent $content): GeneratedCard
    {
        $fingerprint = $this->fingerprint($template, $content);
        $png = $this->store->get($fingerprint);

        if (null === $png) {
            $png = $this->renderer->render($template, $content);
            $this->store->put($fingerprint, $png);
        }

        return new GeneratedCard($fingerprint, $png);
    }
}
