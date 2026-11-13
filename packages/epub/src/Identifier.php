<?php

declare(strict_types=1);

namespace Phil\Epub;

use Phil\Epub\Exception\InvalidBook;

/**
 * An identifier of the publication: a UUID, an ISBN-13, a DOI, a URN or an identifier of the publisher's own.
 */
final readonly class Identifier
{
    private const string UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D';

    /**
     * @throws InvalidBook
     */
    public function __construct(public string $value, public IdentifierType $type = IdentifierType::Proprietary)
    {
        if ('' === trim($value)) {
            throw InvalidBook::because('An identifier cannot be empty.');
        }
    }

    /**
     * A UUID, written `urn:uuid:` followed by its lowercase form.
     *
     * @throws InvalidBook
     */
    public static function uuid(string $uuid): self
    {
        $lower = strtolower($uuid);

        if (1 !== preg_match(self::UUID, $lower)) {
            throw InvalidBook::because(\sprintf('"%s" is not a UUID.', $uuid));
        }

        return new self('urn:uuid:'.$lower, IdentifierType::Uuid);
    }

    /**
     * An ISBN-13, given with or without hyphens and spaces, its check digit verified.
     *
     * @throws InvalidBook
     */
    public static function isbn(string $isbn): self
    {
        $digits = str_replace(['-', ' '], '', $isbn);

        if (1 !== preg_match('/^97[89]\d{10}$/D', $digits)) {
            throw InvalidBook::because(\sprintf('"%s" is not an ISBN-13.', $isbn));
        }

        $sum = 0;

        foreach (str_split(substr($digits, 0, 12)) as $position => $digit) {
            $sum += (int) $digit * (0 === $position % 2 ? 1 : 3);
        }

        if ((10 - $sum % 10) % 10 !== (int) $digits[12]) {
            throw InvalidBook::because(\sprintf('The check digit of the ISBN "%s" is wrong.', $isbn));
        }

        return new self($digits, IdentifierType::Isbn);
    }

    /**
     * A DOI, such as `10.1000/182`.
     *
     * @throws InvalidBook
     */
    public static function doi(string $doi): self
    {
        if (1 !== preg_match('#^10\.\d{4,9}/\S+$#D', $doi)) {
            throw InvalidBook::because(\sprintf('"%s" is not a DOI.', $doi));
        }

        return new self($doi, IdentifierType::Doi);
    }
}
