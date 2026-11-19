<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Domain\ValueObject;

use Phil\LicensingBundle\Domain\Exception\InvalidLicensing;

/**
 * What a declaration is attached to, by type and identifier: a book, a product, a page, a set of rules.
 *
 * The type is 1 to 128 letters, digits, `_`, `.`, `:`, `\` or `-` — a short name such as `book`, or a class name —;
 * the identifier 1 to 64 printable ASCII characters.
 */
final readonly class SubjectReference
{
    private const string TYPE = '/^[A-Za-z0-9_.:\\\\-]{1,128}$/D';

    private const string ID = '/^[\x21-\x7E]{1,64}$/D';

    private function __construct(
        public string $type,
        public string $id,
    ) {
    }

    /**
     * @throws InvalidLicensing
     */
    public static function of(string $type, string|int $id): self
    {
        $id = (string) $id;

        if (1 !== preg_match(self::TYPE, $type)) {
            throw InvalidLicensing::because(\sprintf('The subject type "%s" is 1 to 128 letters, digits, "_", ".", ":", "\\" or "-".', $type));
        }

        if (1 !== preg_match(self::ID, $id)) {
            throw InvalidLicensing::because('The subject identifier is 1 to 64 printable ASCII characters.');
        }

        return new self($type, $id);
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->id === $other->id;
    }
}
