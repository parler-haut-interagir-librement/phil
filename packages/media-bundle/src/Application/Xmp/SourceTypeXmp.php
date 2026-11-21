<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application\Xmp;

use Phil\MediaBundle\Domain\Exception\InvalidMedia;

/**
 * Writes one XMP property, `Iptc4xmpExt:DigitalSourceType`, into an image just rendered: the only metadata a rendering
 * carries. The renderers strip everything else — EXIF, GPS, the XMP of the original —, and the writer drops any EXIF or
 * XMP it still finds in the image before adding its own packet, so that the marking of generated or edited media
 * survives and nothing else does.
 *
 * JPEG gets an `APP1` segment, PNG an `iTXt` chunk, WebP an `XMP ` chunk in the extended format, AVIF a metadata item
 * of type `application/rdf+xml` that describes the primary image. An image the writer cannot extend — another type, or
 * a structure it does not read — is returned unchanged.
 */
final class SourceTypeXmp
{
    /** The XMP property written. */
    public const string XMP_PROPERTY = 'Iptc4xmpExt:DigitalSourceType';

    /** The namespace of the IPTC Extension schema, as XMP declares it. */
    public const string XMP_NAMESPACE = 'http://iptc.org/std/Iptc4xmpExt/2008-02-29/';

    /** The addresses written: those of the IPTC Digital Source Type vocabulary. */
    private const string URI = '{^https?://cv\.iptc\.org/newscodes/digitalsourcetype/[A-Za-z]{1,64}$}D';

    /**
     * The XMP packet that carries the address of a digital source type, and nothing else.
     *
     * @throws InvalidMedia when the address is not one of the vocabulary
     */
    public static function packet(string $uri): string
    {
        if (1 !== preg_match(self::URI, $uri)) {
            throw InvalidMedia::because(\sprintf('"%s" is not the address of an IPTC digital source type.', $uri));
        }

        return '<?xpacket begin="'."\u{FEFF}".'" id="W5M0MpCehiHzreSzNTczkc9d"?>'
            .'<x:xmpmeta xmlns:x="adobe:ns:meta/">'
            .'<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            .'<rdf:Description rdf:about="" xmlns:Iptc4xmpExt="'.self::XMP_NAMESPACE.'" '.self::XMP_PROPERTY.'="'.$uri.'"/>'
            .'</rdf:RDF></x:xmpmeta><?xpacket end="r"?>';
    }

    /**
     * The image with the digital source type written in, in a file type: `jpeg`, `png`, `webp` or `avif`.
     *
     * @throws InvalidMedia when the address is not one of the vocabulary
     */
    public static function write(string $image, string $type, string $uri): string
    {
        $packet = self::packet($uri);

        return match ($type) {
            'jpeg' => JpegXmp::write($image, $packet),
            'png' => PngXmp::write($image, $packet),
            'webp' => WebpXmp::write($image, $packet),
            'avif' => AvifXmp::write($image, $packet),
            default => null,
        } ?? $image;
    }
}
