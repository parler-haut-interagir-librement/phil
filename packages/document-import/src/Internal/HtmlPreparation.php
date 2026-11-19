<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal;

use Dom\HTMLDocument;
use Phil\DocumentImport\Media;
use Phil\DocumentImport\Omitted;

/**
 * HTML made ready for the reader of `phil/markup`: the images written in `data:` addresses, as a word processor pastes
 * them, taken out as media and pointed to their stored address — those that are not images a browser shows are left
 * to the reader, which drops them —, and the elements the reader leaves out — scripts, styles, frames, forms, media
 * players, drawings — reported.
 *
 * @internal
 */
final class HtmlPreparation
{
    private const array EXTENSIONS = ['image/jpeg' => 'jpg', 'image/svg+xml' => 'svg'];

    /** The elements the reader of `phil/markup` leaves out with their content. */
    private const array LEFT_OUT = ['script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'template', 'noscript', 'form', 'input', 'button', 'select', 'textarea', 'svg', 'math', 'audio', 'video', 'canvas'];

    public static function prepare(string $html, Builder $out): string
    {
        $document = HTMLDocument::createFromString(str_replace("\0", "\u{FFFD}", $html), \LIBXML_NOERROR, 'UTF-8');

        foreach ($document->getElementsByTagName('*') as $element) {
            if (\in_array($element->localName, self::LEFT_OUT, true)) {
                $out->report->add(Omitted::Element, $element->localName);
            }
        }

        $number = 0;

        foreach ($document->getElementsByTagName('img') as $image) {
            $source = $image->getAttribute('src') ?? '';

            if (1 !== preg_match('/^data:[^,;]*(;base64)?,/i', $source, $match)) {
                continue;
            }

            $data = substr($source, \strlen($match[0]));
            $bytes = '' === ($match[1] ?? '') ? rawurldecode($data) : (Base64::decode($data) ?? '');
            $type = Media::mediaTypeOf($bytes);
            $url = $out->store(new Media('image-'.++$number.'.'.(self::EXTENSIONS[$type] ?? substr($type, 6)), $bytes, $image->getAttribute('alt') ?? ''));

            if (null !== $url) {
                $image->setAttribute('src', $url);
            }
        }

        return $document->saveHtml();
    }
}
