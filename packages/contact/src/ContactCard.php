<?php

declare(strict_types=1);

namespace Phil\Contact;

use Phil\Contact\Exception\InvalidContact;
use Phil\InstantMessenger\Handle;
use Phil\Url\TypedUrl;

/**
 * A person or an organisation, and how to reach them: emails, phone lines, web addresses and messaging handles. A
 * card names a person when it has a `name`, an organisation when it has only an `organization`. A person's name may
 * also be given in its parts, a `PersonName`, which orders and sorts it as its culture does.
 *
 * The address is not on the card: `phil/address` keeps it.
 */
final readonly class ContactCard
{
    /**
     * @param list<Email>     $emails
     * @param list<PhoneLine> $phones
     * @param list<TypedUrl>  $urls
     * @param list<Handle>    $handles
     */
    private function __construct(
        public ?string $name,
        public ?string $organization,
        public ?string $title,
        public array $emails,
        public array $phones,
        public array $urls,
        public array $handles,
        public ?PersonName $structuredName,
    ) {
    }

    /**
     * Texts are trimmed, and an empty one is none. Without a `name`, a card with a `structuredName` takes its display
     * form as its name.
     *
     * @param list<Email>     $emails
     * @param list<PhoneLine> $phones
     * @param list<TypedUrl>  $urls
     * @param list<Handle>    $handles
     */
    public static function of(?string $name = null, ?string $organization = null, ?string $title = null, array $emails = [], array $phones = [], array $urls = [], array $handles = [], ?PersonName $structuredName = null): self
    {
        return new self(self::text($name, 'name') ?? self::text($structuredName?->display(), 'name'), self::text($organization, 'organization'), self::text($title, 'title'), $emails, $phones, $urls, $handles, $structuredName);
    }

    /**
     * The name a card is known by: the person's, or the organisation's.
     */
    public function displayName(): ?string
    {
        return $this->name ?? $this->organization;
    }

    public function isPerson(): bool
    {
        return null !== $this->name;
    }

    public function isEmpty(): bool
    {
        return null === $this->displayName() && null === $this->title && [] === [...$this->emails, ...$this->phones, ...$this->urls, ...$this->handles];
    }

    /**
     * The phone lines of a role, in their order.
     *
     * @return list<PhoneLine>
     */
    public function phonesFor(PhoneRole $role): array
    {
        return array_values(array_filter($this->phones, static fn (PhoneLine $line): bool => $role === $line->role));
    }

    private static function text(?string $value, string $field): ?string
    {
        $text = trim($value ?? '');

        if (mb_strlen($text) > 255) {
            throw InvalidContact::tooLong($field);
        }

        return '' === $text ? null : $text;
    }
}
