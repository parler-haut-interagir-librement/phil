<?php

declare(strict_types=1);

namespace Phil\ContactBundle\Infrastructure\Http;

use Phil\Contact\JSContactCard;
use Phil\Contact\JSContactWriter;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * A card served as JSContact, `application/jscontact+json`: what JMAP Contacts and recent address books read. With a
 * file name, it is downloaded, a slash or a backslash in the name becoming `_`.
 *
 * ```php
 * return new JSContactResponse(new JSContactCard('urn:uuid:'.$shop->id, $shop->contact(), [$shop->address()]));
 * ```
 */
final class JSContactResponse extends Response
{
    public function __construct(JSContactCard $card, ?string $filename = null)
    {
        $headers = ['Content-Type' => JSContactCard::MEDIA_TYPE];

        if (null !== $filename) {
            $filename = strtr($filename, '/\\', '__');
            $ascii = preg_replace('/[^\x20-\x7e]|%/u', '_', $filename) ?? '';
            $headers['Content-Disposition'] = HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename, '' === trim($ascii, '_. ') ? 'contact.json' : $ascii);
        }

        parent::__construct(JSContactWriter::toJson($card), self::HTTP_OK, $headers);
    }
}
