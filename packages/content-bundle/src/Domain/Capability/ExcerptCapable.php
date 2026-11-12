<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Capability;

use Phil\ContentBundle\Domain\Exception\InvalidContent;

/**
 * A row that can be shown as a teaser — in a list, a card, a feed, a social preview: a title, a short
 * description, and an image.
 *
 * **The image is a reference, not a media model.** A string of printable ASCII: a URL, a path under the
 * application's public directory, or the identifier of a media library. What it points to, how it is resized
 * and served, is the application's; the bundle stores the reference and hands it back. `ExcerptDataMapper`
 * writes it on the unlocalized row, since an image is usually the same in every language, and the title and
 * description on the localized row.
 *
 * Use `ExcerptTrait` for the implementation.
 */
interface ExcerptCapable
{
    public function getExcerptTitle(): ?string;

    /**
     * @throws InvalidContent
     */
    public function setExcerptTitle(?string $excerptTitle): void;

    public function getExcerptDescription(): ?string;

    /**
     * @throws InvalidContent
     */
    public function setExcerptDescription(?string $excerptDescription): void;

    public function getExcerptImage(): ?string;

    /**
     * @throws InvalidContent
     */
    public function setExcerptImage(?string $excerptImage): void;
}
