<?php

declare(strict_types=1);

namespace Phil\SocialCards\Contract;

use Phil\SocialCards\Card\CardContent;
use Phil\SocialCards\Card\CardTemplate;

/**
 * Draws a card: the same template and content give the same image, byte for byte, as long as the version is the same.
 */
interface CardRenderer
{
    /**
     * What the image depends on besides the template and the content — the renderer and its library —, which enters
     * the fingerprint of every card.
     */
    public function version(): string;

    /**
     * The card as a PNG of `CardTemplate::WIDTH` by `CardTemplate::HEIGHT` pixels.
     */
    public function render(CardTemplate $template, CardContent $content): string;
}
