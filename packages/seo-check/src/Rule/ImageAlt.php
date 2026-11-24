<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Rule;

use Override;
use Phil\SeoCheck\CheckedPage;
use Phil\SeoCheck\Contract\SeoRule;
use Phil\SeoCheck\Finding;
use Phil\SeoCheck\Message;
use Phil\SeoCheck\Severity;

/**
 * Every image has an `alt` attribute: search engines read it as the image's text, as screen readers do. An empty
 * `alt=""` is right for an image that is only decoration; so is an image hidden from readers.
 */
final readonly class ImageAlt implements SeoRule
{
    public const string NAME = 'image-alt';

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function check(CheckedPage $page): iterable
    {
        foreach ($page->primary()->markup->images as $image) {
            if (null === $image->alt && !$image->hidden) {
                $src = Excerpt::of($image->src ?? '');

                yield new Finding(self::NAME, Severity::Warning, new Message('image-alt.missing', ['src' => $src]), $src);
            }
        }
    }
}
