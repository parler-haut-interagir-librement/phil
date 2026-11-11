<?php

declare(strict_types=1);

namespace Phil\Slug;

/**
 * Turns a text — a title, a name — into a slug.
 *
 * An application can have several, each a named way of making slugs: one that keeps every word, another that drops
 * leading articles from directory names. A slugger is changed by decorating it: a decorator implements this
 * interface, calls the slugger it wraps, and changes the slug it returns.
 */
interface Slugger
{
    /**
     * @throws InvalidSlug when the text holds no letter or digit a slug can be made of
     */
    public function slug(string $text): Slug;
}
