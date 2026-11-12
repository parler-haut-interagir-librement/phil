<?php

declare(strict_types=1);

namespace Phil\BrokenFlowAlerts;

use Phil\BrokenFlowAlerts\Exception\InvalidFlowValue;

/**
 * A path visitors are expected to finish (a purchase, a registration, a contact form), known by an identifier the
 * application's history understands and a label that people read.
 */
final readonly class Flow
{
    public const int MAX_LENGTH = 128;

    private function __construct(
        public string $id,
        public string $label,
    ) {
    }

    /**
     * @param string      $id    1 to 128 bytes, without surrounding whitespace or control characters
     * @param string|null $label what an alert calls the flow; the identifier when it is `null`
     */
    public static function of(string $id, ?string $label = null): self
    {
        return new self(self::checked('identifier', $id), self::checked('label', $label ?? $id));
    }

    private static function checked(string $kind, string $value): string
    {
        if ('' === $value || \strlen($value) > self::MAX_LENGTH) {
            throw InvalidFlowValue::because(\sprintf('A flow %s is 1 to %d bytes; this one is %d.', $kind, self::MAX_LENGTH, \strlen($value)));
        }

        if (trim($value) !== $value || 1 === preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw InvalidFlowValue::because(\sprintf('A flow %s has no surrounding whitespace and no control character.', $kind));
        }

        return $value;
    }
}
