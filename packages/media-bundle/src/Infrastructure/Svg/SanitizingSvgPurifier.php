<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\Svg;

use Override;
use Phil\MediaBundle\Application\Contract\SvgPurifier;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\SvgSanitizer\SvgSanitizer;

/**
 * Purifies an SVG image with phil/svg-sanitizer — no script, event handler, `foreignObject` or external reference, CSS
 * through its policy, size and depth bounded — then makes it smaller with the `SvgMinifier`. An image the sanitizer
 * refuses whole is refused.
 */
final readonly class SanitizingSvgPurifier implements SvgPurifier
{
    public function __construct(
        private SvgSanitizer $sanitizer = new SvgSanitizer(),
        private SvgMinifier $minifier = new SvgMinifier(),
    ) {
    }

    #[Override]
    public function purify(string $svg): string
    {
        $sanitized = $this->sanitizer->sanitize($svg);

        if ($sanitized->isRefused()) {
            throw InvalidMedia::because(\sprintf('The SVG image is refused: %s.', $sanitized->removals[0]->reason->value ?? 'unreadable'));
        }

        return $this->minifier->minify($sanitized->svg);
    }
}
