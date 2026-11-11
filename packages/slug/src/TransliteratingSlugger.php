<?php

declare(strict_types=1);

namespace Phil\Slug;

use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Makes a slug by transliterating a text to ASCII, in lowercase words joined by hyphens: `Jeux de rôle` is
 * `jeux-de-role`, `L'Ordre du Corbeau` is `l-ordre-du-corbeau`.
 *
 * The transliteration is that of `symfony/string`, in the given locale when there is one (`de` writes `ü` as
 * `ue`). A slug longer than the maximum length is cut at the last hyphen that fits, so that no word is cut in two;
 * a first word longer than the maximum is cut where the maximum falls.
 */
final readonly class TransliteratingSlugger implements Slugger
{
    private AsciiSlugger $slugger;

    /**
     * @throws InvalidSlug when the maximum length is not between 1 and 255
     */
    public function __construct(
        ?string $locale = null,
        private int $maxLength = Slug::MAX_LENGTH,
    ) {
        if ($maxLength < 1 || $maxLength > Slug::MAX_LENGTH) {
            throw InvalidSlug::because(\sprintf('The maximum length of a slug is between 1 and %d; %d is not.', Slug::MAX_LENGTH, $maxLength));
        }

        $this->slugger = new AsciiSlugger($locale);
    }

    public function slug(string $text): Slug
    {
        $slug = $this->slugger->slug($text)->lower()->toString();

        if ('' === $slug) {
            throw InvalidSlug::because(\sprintf('"%s" holds no letter or digit to make a slug of.', $text));
        }

        return Slug::fromString($this->shortened($slug));
    }

    private function shortened(string $slug): string
    {
        if (\strlen($slug) <= $this->maxLength) {
            return $slug;
        }

        $cut = substr($slug, 0, $this->maxLength + 1);
        $hyphen = strrpos($cut, '-');

        return false === $hyphen ? substr($slug, 0, $this->maxLength) : substr($cut, 0, $hyphen);
    }
}
