<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Url;

use Override;
use Phil\CssSanitizer\UrlKind;
use Phil\CssSanitizer\UrlRewriter;

/**
 * Allows the files of a project, and nothing else: each name a style sheet may write in `url()` maps to the public URL
 * of that file, images, fonts and the style sheets of `@import` apart. A name may be written with a leading `./`. Any other URL is refused, whether it
 * points to another site or to another file of the same one.
 */
final readonly class AllowedFiles implements UrlRewriter
{
    /**
     * @param array<string, string> $images      the public URL of each image, by the name a style sheet writes
     * @param array<string, string> $fonts       the public URL of each font, by the name a style sheet writes
     * @param array<string, string> $styleSheets the public URL of each style sheet `@import` may load, by its name
     */
    public function __construct(
        private array $images = [],
        private array $fonts = [],
        private array $styleSheets = [],
    ) {
    }

    #[Override]
    public function rewrite(string $url, UrlKind $kind): ?string
    {
        $files = match ($kind) {
            UrlKind::Image => $this->images,
            UrlKind::Font => $this->fonts,
            UrlKind::StyleSheet => $this->styleSheets,
        };
        $name = str_starts_with($url, './') ? substr($url, 2) : $url;

        return $files[$name] ?? null;
    }
}
