<?php

declare(strict_types=1);

namespace Phil\SamlSp\Attribute;

use DateTimeImmutable;

/**
 * The person an accepted assertion signs in: who issued it, the name identifier and its format, the session at the
 * identity provider, the attributes as sent, and the fields of the user mapped from them.
 */
final readonly class SamlIdentity
{
    /**
     * @param array<string, list<string>>              $attributes every attribute of the assertion, by name
     * @param array<value-of<UserField>, list<string>> $fields     the fields of the user, by the attribute map
     */
    public function __construct(
        public string $issuer,
        public string $nameId,
        public ?string $nameIdFormat,
        public ?string $sessionIndex,
        public DateTimeImmutable $authnInstant,
        public ?DateTimeImmutable $sessionNotOnOrAfter,
        public ?string $authnContext,
        public string $assertionId,
        public array $attributes,
        public array $fields,
    ) {
    }

    /**
     * The stable identifier of the person: the mapped identifier, else the name identifier.
     */
    public function subject(): string
    {
        return $this->value(UserField::Identifier) ?? $this->nameId;
    }

    public function value(UserField $field): ?string
    {
        return $this->fields[$field->value][0] ?? null;
    }

    /**
     * @return list<string>
     */
    public function values(UserField $field): array
    {
        return $this->fields[$field->value] ?? [];
    }
}
