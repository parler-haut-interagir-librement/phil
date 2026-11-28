<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Application\Rendering;

use Phil\EmailTemplatesBundle\Domain\Status\TemplateOrigin;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;

/**
 * An email rendered and not sent: its subject, its preheader, its whole HTML document with the styles inlined, its
 * plain-text part and its embedded images, with the template, site, locale and version it was rendered from.
 */
final readonly class RenderedEmail
{
    /** The size above which Gmail clips a message and hides the rest behind a link. */
    public const int GMAIL_CLIP_BYTES = 102 * 1024;

    /**
     * @param int|null            $version  the version of the site's or the shared template, `null` for a default file
     * @param list<EmbeddedImage> $embeds
     * @param list<string>        $segments
     */
    public function __construct(
        public TemplateKey $key,
        public Site $site,
        public Locale $locale,
        public ?int $version,
        public TemplateOrigin $origin,
        public string $subject,
        public string $preheader,
        public string $html,
        public string $text,
        public array $embeds = [],
        public array $segments = [],
    ) {
    }

    /**
     * Whether Gmail would clip the HTML, which hides the end of the message and its footer.
     */
    public function isClippedByGmail(): bool
    {
        return \strlen($this->html) > self::GMAIL_CLIP_BYTES;
    }

    /**
     * The HTML with each embedded image as a `data:` address: what a browser shows in a preview.
     */
    public function htmlForBrowser(): string
    {
        $replacements = [];

        foreach ($this->embeds as $embed) {
            $replacements['cid:'.$embed->name.'"'] = $embed->dataUri().'"';
            $replacements['cid:'.$embed->name."'"] = $embed->dataUri()."'";
        }

        return [] === $replacements ? $this->html : strtr($this->html, $replacements);
    }
}
