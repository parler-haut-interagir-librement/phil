<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Contract;

use Phil\ContentBundle\Domain\Draft\WorkingCopy;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\LocaleDimensions\Locale;

/**
 * Where working copies are kept: the autosaves of each author and the named drafts of each content.
 */
interface WorkingCopyRepository
{
    public function findAutosave(ContentReference $content, Locale $locale, string $author): ?WorkingCopy;

    public function findNamed(ContentReference $content, Locale $locale, string $name): ?WorkingCopy;

    /**
     * The named drafts of a content in a locale, by name.
     *
     * @return list<WorkingCopy>
     */
    public function findAllNamed(ContentReference $content, Locale $locale): array;

    /**
     * Every working copy of a content, autosaves and named drafts, in every locale: what deleting the content removes.
     *
     * @return list<WorkingCopy>
     */
    public function findAllOf(ContentReference $content): array;

    public function add(WorkingCopy $copy): void;

    public function remove(WorkingCopy $copy): void;
}
