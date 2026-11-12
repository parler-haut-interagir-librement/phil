<?php

declare(strict_types=1);

namespace Phil\Contact;

use Phil\Address\Address;
use Phil\Contact\Exception\InvalidContact;

/**
 * A contact card as JSContact (RFC 9553) exchanges it: its `uid`, the card, its postal addresses — which
 * `phil/address` holds and the card does not —, the properties the library does not map, kept as they were read so
 * that they are written back, and the notices of what was read but not kept.
 *
 * The unmapped properties are JSON values as `json_decode()` gives them without its associative flag: objects are
 * `stdClass`, so that an empty object stays one.
 */
final readonly class JSContactCard
{
    public const string MEDIA_TYPE = 'application/jscontact+json';

    /**
     * The properties `JSContactWriter` writes itself, which the unmapped properties never hold.
     */
    public const array MAPPED = ['@type', 'version', 'uid', 'kind', 'name', 'organizations', 'titles', 'emails', 'phones', 'onlineServices', 'links', 'addresses'];

    /**
     * @param list<Address>         $addresses
     * @param array<string, mixed>  $unmapped
     * @param list<JSContactNotice> $notices
     */
    public function __construct(
        public string $uid,
        public ContactCard $card,
        public array $addresses = [],
        public array $unmapped = [],
        public array $notices = [],
    ) {
        if ('' === trim($uid) || \strlen($uid) > 1024) {
            throw InvalidContact::because('The uid of a JSContact card is a text of 1 to 1024 bytes.');
        }

        foreach (array_keys($unmapped) as $property) {
            if (\in_array($property, self::MAPPED, true)) {
                throw InvalidContact::because(\sprintf('The property "%s" of a JSContact card is written from the card.', $property));
            }
        }
    }
}
