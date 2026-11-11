<?php

declare(strict_types=1);

namespace Phil\MarkupBundle\Infrastructure\Twig;

use Phil\Markup\Exception\InvalidSource;
use Phil\Markup\HtmlPurifier;
use Phil\Markup\MarkedUpText;
use Phil\Markup\Markup;
use Phil\MarkupBundle\Infrastructure\Conversion\UjoConverter;
use Phil\MarkupBundle\Infrastructure\Format\MarkupFormats;
use Phil\MarkupBundle\Infrastructure\Format\UnavailableFormat;
use Twig\Attribute\AsTwigFilter;

/**
 * Markup in templates: `{{ source|markup_html('bbcode', 'forum') }}` compiles and purifies a source,
 * `{{ post.body|markup_purify }}` purifies again the HTML a text was stored with, under the policy of today, and
 * `{{ source|markup_ujo('markdown') }}` gives the UJO JSON of a source, for a block editor to open.
 */
final readonly class MarkupExtension
{
    /** The UJO JSON of a document without blocks. */
    public const string EMPTY_DOCUMENT = '{"blocks":[]}';

    public function __construct(
        private Markup $markup,
        private HtmlPurifier $purifier,
        private MarkupFormats $formats,
        private UjoConverter $converter,
    ) {
    }

    /**
     * The HTML of a source in an enabled format — the default one when none is named —, purified at a level — the
     * configured one when none is named —, lowered to the format's ceiling. A format not enabled or a level unknown is
     * an error.
     *
     * @throws UnavailableFormat
     * @throws InvalidSource     when a UJO source is not valid
     */
    #[AsTwigFilter(name: 'markup_html', isSafe: ['html'])]
    public function markup(?string $source, ?string $format = null, ?string $level = null): string
    {
        if (null === $source) {
            return '';
        }

        $format = $this->formats->format($format);

        return $this->markup->toHtml($source, $format, $this->formats->level($format, $level));
    }

    /**
     * Stored HTML, or the HTML of a marked-up text, purified at a level — the configured one when none is named —: a
     * marked-up text is kept to its format's ceiling, whether its format is still enabled or not.
     */
    #[AsTwigFilter(name: 'markup_purify', isSafe: ['html'])]
    public function purify(MarkedUpText|string|null $html, ?string $level = null): string
    {
        return match (true) {
            null === $html => '',
            $html instanceof MarkedUpText => $this->purifier->purify($html->html, $this->formats->level($html->format, $level)),
            default => $this->purifier->purify($html, $this->formats->level(null, $level)),
        };
    }

    /**
     * The UJO JSON of a source in an enabled format, read at a level; a document without blocks for `null`. Twig
     * escapes it as any text, for an attribute or a script.
     *
     * @throws UnavailableFormat
     * @throws InvalidSource     when a UJO source is not valid
     */
    #[AsTwigFilter(name: 'markup_ujo')]
    public function ujo(?string $source, ?string $format = null, ?string $level = null): string
    {
        return null === $source ? self::EMPTY_DOCUMENT : $this->converter->toJson($source, $format, $level);
    }
}
