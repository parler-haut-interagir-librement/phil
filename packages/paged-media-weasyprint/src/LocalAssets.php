<?php

declare(strict_types=1);

namespace Phil\PagedMediaWeasyprint;

use Dom\HTMLDocument;
use InvalidArgumentException;
use Phil\PagedMediaWeasyprint\Exception\WeasyPrintFailed;
use Phil\PagedMediaWeasyprint\Internal\AssetInlining;

/**
 * The directory a document may take its images, fonts and colour profiles from.
 *
 * A relative reference to an image, a font or an ICC profile of the directory is replaced by a `data:` address
 * holding the file. Everything else is left as it is written, and the engine refuses to fetch it: what this class does
 * not recognize is never read.
 */
final readonly class LocalAssets
{
    /** The elements that show a file, with the attributes that name it. */
    private const array ATTRIBUTES = [
        'img' => ['src'],
        'embed' => ['src'],
        'object' => ['data'],
        'image' => ['href', 'xlink:href'],
    ];

    /** @var positive-int */
    private int $maxFileBytes;

    private int $maxTotalBytes;

    /**
     * @param string $directory     the directory; it is resolved at each print, so that it may be a link a deployment switches
     * @param int    $maxFileBytes  the largest file that may be taken
     * @param int    $maxTotalBytes the most a document may take in all, a file used twice counting twice
     */
    public function __construct(
        private string $directory,
        int $maxFileBytes = 10_485_760,
        int $maxTotalBytes = 52_428_800,
    ) {
        if ($maxFileBytes < 1 || $maxTotalBytes < 1) {
            throw new InvalidArgumentException('The limits of the local assets must be positive.');
        }

        $this->maxFileBytes = $maxFileBytes;
        $this->maxTotalBytes = $maxTotalBytes;
    }

    /**
     * @param string $html the whole document
     *
     * @return string the document, with the files of the directory written into it
     */
    public function inline(string $html): string
    {
        $root = realpath($this->directory);

        if (false === $root || !is_dir($root)) {
            throw WeasyPrintFailed::noAssetsDirectory($this->directory);
        }

        $inlining = new AssetInlining($root, $this->maxFileBytes, $this->maxTotalBytes);
        $document = HTMLDocument::createFromString(str_replace("\0", "\u{FFFD}", $html), \LIBXML_NOERROR, 'UTF-8');

        foreach ($document->getElementsByTagName('*') as $element) {
            foreach (self::ATTRIBUTES[$element->localName] ?? [] as $attribute) {
                $address = $inlining->address($element->getAttribute($attribute) ?? '');

                if (null !== $address) {
                    $element->setAttribute($attribute, $address);
                }
            }

            $style = $element->getAttribute('style');

            if (null !== $style) {
                $element->setAttribute('style', $inlining->stylesheet($style));
            }

            if ('style' === $element->localName) {
                $element->textContent = $inlining->stylesheet($element->textContent ?? '');
            }
        }

        return $inlining->changed() ? $document->saveHtml() : $html;
    }
}
