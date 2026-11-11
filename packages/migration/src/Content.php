<?php

declare(strict_types=1);

namespace Phil\Migration;

/**
 * What a channel announces: the release notes, or the news.
 */
enum Content: string
{
    case Notes = 'notes';
    case News = 'news';
}
