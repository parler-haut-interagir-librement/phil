<?php

declare(strict_types=1);

namespace Phil\QrCodeBundle\Application;

use DateTimeImmutable;
use Exception;
use Phil\QrCode\Content\Email;
use Phil\QrCode\Content\Event;
use Phil\QrCode\Content\Geo;
use Phil\QrCode\Content\MeCard;
use Phil\QrCode\Content\Phone;
use Phil\QrCode\Content\PostalAddress;
use Phil\QrCode\Content\Sms;
use Phil\QrCode\Content\Url;
use Phil\QrCode\Content\VCard;
use Phil\QrCode\Content\VCardVersion;
use Phil\QrCode\Content\Wifi;
use Phil\QrCode\Content\WifiSecurity;
use Phil\QrCode\Contract\Content;
use Phil\QrCode\Exception\InvalidContent;

/**
 * A typed content from the array a template, a UJO block or a form gives: `{type: 'wifi', ssid: '…', password: '…'}`.
 * Each type takes the keys of its content in snake case; a key it does not know, or a value of the wrong type, is
 * refused.
 */
final class ContentFactory
{
    /** The keys each type takes. */
    public const array KEYS = [
        'url' => ['url'],
        'email' => ['to', 'subject', 'body', 'cc', 'bcc'],
        'phone' => ['number'],
        'sms' => ['number', 'message'],
        'geo' => ['latitude', 'longitude', 'altitude', 'uncertainty', 'name'],
        'wifi' => ['ssid', 'password', 'security', 'hidden'],
        'mecard' => ['name', 'phones', 'emails', 'url', 'address', 'note', 'birthday'],
        'vcard' => ['name', 'family_name', 'given_name', 'organization', 'title', 'phones', 'emails', 'urls', 'address', 'birthday', 'note', 'version'],
        'event' => ['summary', 'start', 'end', 'location', 'description', 'url', 'all_day', 'uid'],
    ];

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidContent when the type is unknown, a key is not one of its keys, or a value is not of its type
     */
    public static function fromArray(array $data): Content
    {
        $type = $data['type'] ?? null;

        if (!\is_string($type) || !isset(self::KEYS[$type])) {
            throw InvalidContent::of('type of the content', 'one of '.implode(', ', array_keys(self::KEYS)));
        }

        unset($data['type']);
        $unknown = array_diff(array_keys($data), self::KEYS[$type]);

        if ([] !== $unknown) {
            throw InvalidContent::of('content of type '.$type, 'made of the keys '.implode(', ', self::KEYS[$type]).'; not '.implode(', ', $unknown));
        }

        $d = new Fields($data);

        return match ($type) {
            'url' => new Url($d->string('url')),
            'email' => new Email($d->strings('to'), $d->string('subject'), $d->string('body'), $d->strings('cc'), $d->strings('bcc')),
            'phone' => new Phone($d->string('number')),
            'sms' => new Sms($d->string('number'), $d->string('message')),
            'geo' => new Geo($d->number('latitude') ?? 0.0, $d->number('longitude') ?? 0.0, $d->number('altitude'), $d->number('uncertainty'), $d->string('name')),
            'wifi' => new Wifi($d->string('ssid'), $d->string('password'), WifiSecurity::tryFrom($d->string('security', 'WPA')) ?? throw InvalidContent::of('security', 'WPA, WEP or nopass'), $d->bool('hidden')),
            'mecard' => new MeCard($d->string('name'), $d->strings('phones'), $d->strings('emails'), $d->string('url'), $d->string('address'), $d->string('note'), self::date($d->string('birthday'), 'birthday')),
            'vcard' => self::vcard($d),
            default => self::event($d),
        };
    }

    private static function vcard(Fields $d): VCard
    {
        $address = $d->fields('address', ['street', 'locality', 'region', 'postal_code', 'country']);

        return new VCard(
            $d->string('name'),
            $d->string('family_name'),
            $d->string('given_name'),
            $d->string('organization'),
            $d->string('title'),
            $d->strings('phones'),
            $d->strings('emails'),
            $d->strings('urls'),
            $address instanceof Fields ? new PostalAddress($address->string('street'), $address->string('locality'), $address->string('region'), $address->string('postal_code'), $address->string('country')) : null,
            self::date($d->string('birthday'), 'birthday'),
            $d->string('note'),
            VCardVersion::tryFrom($d->string('version', '3.0')) ?? throw InvalidContent::of('version of the vCard', '3.0 or 4.0'),
        );
    }

    private static function event(Fields $d): Event
    {
        $start = self::date($d->string('start'), 'start of the event') ?? throw InvalidContent::of('start of the event', 'a date');

        return new Event($d->string('summary'), $start, self::date($d->string('end'), 'end of the event'), $d->string('location'), $d->string('description'), $d->string('url'), $d->bool('all_day'), $d->string('uid'));
    }

    private static function date(string $value, string $field): ?DateTimeImmutable
    {
        if ('' === $value) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            throw InvalidContent::of($field, 'a date, as 2026-11-08 or 2026-11-08T19:00:00-05:00');
        }
    }
}
