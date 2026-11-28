<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo;

use JsonException;
use Phil\ParticipantSafetyInfo\Exception\InvalidSafetyInfo;
use Phil\ParticipantSafetyInfo\Exception\UnreadableSafetyInfo;
use SensitiveParameter;

/**
 * The plaintext form of safety information, sealed and opened by the vault: a JSON object of version 1 with exactly
 * the keys `v`, `contacts`, `allergies`, `needs` and `notes`.
 *
 * Decoding is strict and bounded: 32 KiB at most, a depth of 4, no key more or less, every value of its type, and
 * every value checked again by the constructors. Anything else is refused as unreadable.
 */
final class SafetyInfoCodec
{
    public const int MAX_BYTES = 32_768;

    private const int VERSION = 1;

    private const array KEYS = ['allergies', 'contacts', 'needs', 'notes', 'v'];

    private const array CONTACT_KEYS = ['name', 'phone', 'relation'];

    public static function encode(SafetyInfo $info): string
    {
        return json_encode([
            'v' => self::VERSION,
            'contacts' => array_map(static fn (EmergencyContact $contact): array => [
                'name' => $contact->name,
                'phone' => $contact->phone,
                'relation' => $contact->relation,
            ], $info->contacts),
            'allergies' => $info->allergies,
            'needs' => $info->needs,
            'notes' => $info->notes,
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }

    /**
     * @throws UnreadableSafetyInfo when the text is not safety information of version 1
     */
    public static function decode(#[SensitiveParameter] string $json): SafetyInfo
    {
        if (\strlen($json) > self::MAX_BYTES) {
            throw UnreadableSafetyInfo::because(\sprintf('Safety information holds %d bytes at most.', self::MAX_BYTES));
        }

        try {
            $data = json_decode($json, true, 4, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw UnreadableSafetyInfo::because('Safety information is not valid JSON.', $jsonException);
        }

        if (!\is_array($data) || !self::hasExactly($data, self::KEYS) || self::VERSION !== $data['v']) {
            throw UnreadableSafetyInfo::because('Safety information is not an object of version 1 with its five keys.');
        }

        $contacts = $data['contacts'];

        if (!\is_array($contacts) || !array_is_list($contacts) || \count($contacts) > SafetyInfo::MAX_CONTACTS) {
            throw UnreadableSafetyInfo::because(\sprintf('The contacts of safety information are a list of %d at most.', SafetyInfo::MAX_CONTACTS));
        }

        if (!\is_string($data['allergies']) || !\is_string($data['needs']) || !\is_string($data['notes'])) {
            throw UnreadableSafetyInfo::because('The fields of safety information are strings.');
        }

        try {
            return new SafetyInfo(array_map(self::contact(...), $contacts), $data['allergies'], $data['needs'], $data['notes']);
        } catch (InvalidSafetyInfo $invalidSafetyInfo) {
            throw UnreadableSafetyInfo::because('Safety information breaks a rule of its fields.', $invalidSafetyInfo);
        }
    }

    private static function contact(mixed $contact): EmergencyContact
    {
        if (!\is_array($contact) || !self::hasExactly($contact, self::CONTACT_KEYS)
            || !\is_string($contact['name']) || !\is_string($contact['phone']) || !\is_string($contact['relation'])) {
            throw UnreadableSafetyInfo::because('An emergency contact is an object of three strings: name, phone and relation.');
        }

        return new EmergencyContact($contact['name'], $contact['phone'], $contact['relation']);
    }

    /**
     * @param array<mixed> $data
     * @param list<string> $keys sorted
     */
    private static function hasExactly(array $data, array $keys): bool
    {
        $actual = array_keys($data);
        sort($actual);

        return $actual === $keys;
    }
}
