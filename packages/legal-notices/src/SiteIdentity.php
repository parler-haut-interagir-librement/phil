<?php

declare(strict_types=1);

namespace Phil\LegalNotices;

use Phil\LegalNotices\Exception\InvalidLegalNotice;

/**
 * Who the site is, field by field: the values its documents name and the ones each document requires. A value is
 * trimmed text of 1,000 bytes at most, on one line but for the addresses; an empty value or `null` is a field left
 * out; the contact address is an e-mail address.
 */
final readonly class SiteIdentity
{
    public const int MAX_BYTES = 1000;

    /**
     * @var array<string, non-empty-string> by field
     */
    private array $values;

    /**
     * @param array<array-key, mixed> $values by field name
     *
     * @throws InvalidLegalNotice
     */
    public function __construct(array $values = [])
    {
        $kept = [];

        foreach ($values as $name => $value) {
            $field = Field::tryFrom((string) $name) ?? throw InvalidLegalNotice::because('unknown_field', \sprintf('%s is not a field of the identity.', InvalidLegalNotice::quote((string) $name)));

            $value = $this->value($field, $value);

            if (null !== $value) {
                $kept[$field->value] = $value;
            }
        }

        $this->values = $kept;
    }

    public function get(Field $field): ?string
    {
        return $this->values[$field->value] ?? null;
    }

    /**
     * The values given, by field name.
     *
     * @return array<string, non-empty-string>
     */
    public function values(): array
    {
        return $this->values;
    }

    /**
     * The fields a document requires and the identity lacks, in the order of the document.
     *
     * @return list<Field>
     */
    public function missing(DocumentType $type): array
    {
        return array_values(array_filter($type->requiredFields(), fn (Field $field): bool => !isset($this->values[$field->value])));
    }

    /**
     * @return non-empty-string|null
     */
    private function value(Field $field, mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw InvalidLegalNotice::because('invalid_field', \sprintf('The field "%s" is text.', $field->value));
        }

        $value = trim(str_replace(["\r\n", "\r"], "\n", $value));

        if ('' === $value) {
            return null;
        }

        $controls = $field->isMultiline() ? '/[\x00-\x09\x0B-\x1F\x7F]/' : '/[\x00-\x1F\x7F]/';

        if (\strlen($value) > self::MAX_BYTES || !mb_check_encoding($value, 'UTF-8') || 1 === preg_match($controls, $value)) {
            throw InvalidLegalNotice::because('invalid_field', \sprintf('The field "%s" is UTF-8 text of 1000 bytes at most, without control characters%s.', $field->value, $field->isMultiline() ? '' : ', on one line'));
        }

        if (Field::ContactEmail === $field && false === filter_var($value, \FILTER_VALIDATE_EMAIL)) {
            throw InvalidLegalNotice::because('invalid_email', \sprintf('The field "contact_email" is an e-mail address: %s is not.', InvalidLegalNotice::quote($value)));
        }

        return $value;
    }
}
