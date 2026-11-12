<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Rule;

use Uri\WhatWg\Url;

/**
 * Whether a text alternative is a file name rather than a description: `photo.jpg`, `IMG_2041`, `DSC0001`, `scan 3`,
 * `untitled`, or the name of the image's own file, with or without its extension.
 *
 * The image's file is the last segment of its address, read as a browser reads it, by PHP's WHATWG URL parser on which
 * `phil/url` rests: relative or absolute, a backslash taken for a slash, the query, the fragment and any user
 * information left out, the segment's escapes decoded.
 */
final class FileName
{
    private const string WITH_EXTENSION = '/^[^\s\/\\\\]+\.(?:jpe?g|png|gif|webp|avif|svg|bmp|tiff?|heic|heif)$/iD';

    private const string CAMERA = '/^(?:img|dsc[nf]?|dcim|pxl|mvimg|screenshot|capture|photo|image|scan|untitled)[\s_-]*\d*$/iD';

    /**
     * What a relative address is read against: only its path is kept.
     */
    private const string BASE = 'https://source.invalid/';

    public static function looksLikeOne(string $alternative, string $source): bool
    {
        $alternative = trim($alternative);

        if (1 === preg_match(self::WITH_EXTENSION, $alternative) || 1 === preg_match(self::CAMERA, $alternative)) {
            return true;
        }

        $file = rawurldecode(basename(Url::parse($source, new Url(self::BASE))?->getPath() ?? ''));

        return '' !== $file && (0 === strcasecmp($alternative, $file) || 0 === strcasecmp($alternative, pathinfo($file, \PATHINFO_FILENAME)));
    }
}
