<?php

declare(strict_types=1);

namespace Phil\EventWeather;

use Phil\EventWeather\Exception\InvalidArgument;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;

/**
 * Who sends the requests, as the providers ask to know it: the application's name and version, and a way to reach
 * whoever runs it — a web address or an email address. MET Norway refuses anonymous clients and bans made-up names;
 * the application supplies its own, never one copied from an example.
 *
 * The `User-Agent` reads `EventsApp/1.4 https://events.example` or `EventsApp/1.4 ops@events.example`.
 */
final readonly class Contact
{
    /**
     * @throws InvalidArgument when the name is not a product token with an optional version, or the contact is neither
     *                         an http or https address nor an email address
     */
    public function __construct(
        public string $application,
        public string $contact,
    ) {
        if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}(?:\/[A-Za-z0-9._+-]{1,32})?$/D', $application)) {
            throw new InvalidArgument('The application is named as a User-Agent product, such as "EventsApp/1.4".');
        }

        if (!$this->isEmail($contact) && !$this->isUrl($contact)) {
            throw new InvalidArgument('The contact is an http or https address or an email address.');
        }
    }

    public function userAgent(): string
    {
        return $this->application.' '.$this->contact;
    }

    private function isEmail(string $contact): bool
    {
        return 1 === preg_match('/^[A-Za-z0-9.!#$%&\'*+\/=?^_`{|}~-]{1,64}@[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)+$/D', $contact);
    }

    private function isUrl(string $contact): bool
    {
        try {
            Url::fromString($contact);

            return 1 !== preg_match('/[\s\x00-\x1F\x7F]/', $contact);
        } catch (InvalidUrl) {
            return false;
        }
    }
}
