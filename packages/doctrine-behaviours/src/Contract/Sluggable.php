<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Contract;

use Phil\Slug\Slug;

/**
 * A record with a slug of its own, kept unique in its table or within a scope of it: `SlugListener` makes it from the
 * text the record names as it is first flushed, and leaves it alone afterwards, so that addresses last. A slug given is
 * kept, once it is unique; `assignSlug(null)` asks for a new one at the next flush.
 */
interface Sluggable
{
    public const string SLUG_FIELD = 'slug';

    public function slug(): ?string;

    /**
     * The text the slug is made of, usually a title.
     */
    public function slugSource(): string;

    /**
     * The name of the slugger that makes this slug; null for the default one.
     */
    public function sluggerName(): ?string;

    /**
     * The fields within whose values the slug is unique: a tenant, a locale, a parent. Each is a column of the record or
     * a to-one relation it holds by one column. An empty list keeps the slug unique in the whole table.
     *
     * @return list<string>
     */
    public function slugScope(): array;

    public function assignSlug(?Slug $slug): void;
}
