<?php

declare(strict_types=1);

namespace Phil\Epub\Internal;

use Closure;
use Dom\HTMLDocument;

/**
 * Purified HTML turned into well-formed XHTML: the fragment is parsed as HTML5, then written back as XML — elements in
 * their namespace, void elements closed, text and attribute values escaped, characters XML forbids removed.
 *
 * What a content document cannot hold is left out: scripts, templates, `style`, `link`, `meta`, `title` and `base`
 * elements, `object` and `embed`, event handler attributes, `srcset`, attributes whose name XML refuses. Duplicate
 * identifiers are dropped after the first. Every link goes through `$link`, every resource through `$resource`, which
 * return the address to write or null; an image without address leaves its text alternative, other media disappear.
 *
 * @internal
 */
final class XhtmlConverter
{
    /**
     * @param Closure(string): ?string         $link
     * @param Closure(string, string): ?string $resource
     */
    public function convert(string $html, Closure $link, Closure $resource): Converted
    {
        $body = HTMLDocument::createEmpty()->createElement('body');
        $body->innerHTML = $html;

        return new XhtmlSerialization($link, $resource, $body)->run();
    }
}
