<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Content;

use Closure;
use Dom\Element;
use Dom\HTMLDocument;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;

/**
 * Rewrites the links of a message's body for click tracking: each `http` or `https` address read by `phil/url` is
 * replaced by the address a function gives for it; `mailto:`, anchors and anything unreadable are left as they are.
 * The body is read with PHP's HTML5 parser, as a browser reads it.
 */
final readonly class LinkRewriter
{
    /**
     * @param Closure(string): string $track the tracked address of a link
     */
    public function rewrite(string $html, Closure $track): string
    {
        if (!str_contains($html, '<a')) {
            return $html;
        }

        $document = HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', \LIBXML_NOERROR);
        $body = $document->body;

        if (!$body instanceof Element) {
            return $html;
        }

        foreach ($body->querySelectorAll('a[href]') as $anchor) {
            $href = trim((string) $anchor->getAttribute('href'));

            try {
                $url = Url::fromString($href);
            } catch (InvalidUrl) {
                continue;
            }

            if (\in_array($url->scheme, ['http', 'https'], true)) {
                $anchor->setAttribute('href', $track($url->toString()));
            }
        }

        return $body->innerHTML;
    }
}
