<?php

declare(strict_types=1);

namespace Phil\ContactBundle\Infrastructure\Http;

use Phil\Contact\ContactCard;
use Phil\Contact\VCard;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * A card downloaded as a `.vcf` file, which address books import. A slash or a backslash in the file name, which
 * names no folder in a download, becomes `_`.
 *
 * ```php
 * return new VCardResponse($shop->contact(), 'shop.vcf');
 * ```
 */
final class VCardResponse extends Response
{
    public function __construct(ContactCard $card, string $filename = 'contact.vcf')
    {
        $filename = strtr($filename, '/\\', '__');

        parent::__construct(VCard::of($card), self::HTTP_OK, [
            'Content-Type' => VCard::MEDIA_TYPE.'; charset=utf-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename, $this->fallback($filename)),
        ]);
    }

    /**
     * The file name in ASCII, for clients that read no other.
     */
    private function fallback(string $filename): string
    {
        $ascii = preg_replace('/[^\x20-\x7e]|%/u', '_', $filename) ?? '';

        return '' === trim($ascii, '_. ') ? 'contact.vcf' : $ascii;
    }
}
