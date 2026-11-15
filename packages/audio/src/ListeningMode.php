<?php

declare(strict_types=1);

namespace Phil\Audio;

/**
 * What a listener who has not bought a track may hear of it: the whole track, an excerpt, or nothing.
 */
enum ListeningMode: string
{
    case Full = 'full';
    case Excerpt = 'excerpt';
    case PurchaseOnly = 'purchase_only';
}
