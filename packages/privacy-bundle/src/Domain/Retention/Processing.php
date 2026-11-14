<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Retention;

use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

/**
 * What a register of processing says of a store beside how long it keeps a record: why it holds it, who receives it,
 * which service providers handle it, and where it is kept.
 *
 * Every part is optional, so that a schedule can be written before its register is complete; `ProcessingRegister`
 * names the stores holding personal information whose purpose or location is still missing.
 */
final readonly class Processing
{
    public const int MAX_LINE = 200;

    public const int MAX_PURPOSE = 1000;

    /**
     * @var list<string>
     */
    public array $recipients;

    /**
     * @var list<Processor>
     */
    public array $processors;

    /**
     * @param list<string>    $recipients who receives the records: a team, a partner, an authority
     * @param list<Processor> $processors
     */
    public function __construct(
        public ?string $purpose = null,
        array $recipients = [],
        array $processors = [],
        public ?string $location = null,
    ) {
        if (null !== $purpose && ('' === trim($purpose) || mb_strlen($purpose) > self::MAX_PURPOSE)) {
            throw InvalidPrivacyValue::because(\sprintf('A purpose is a text of 1 to %d characters.', self::MAX_PURPOSE));
        }

        if (null !== $location) {
            $this->line('location', $location);
        }

        $seen = [];

        foreach ($recipients as $recipient) {
            $this->line('recipient', $recipient);

            if (isset($seen[$recipient])) {
                throw InvalidPrivacyValue::because(\sprintf('The recipient "%s" is listed twice.', $recipient));
            }

            $seen[$recipient] = $recipient;
        }

        $names = [];

        foreach ($processors as $processor) {
            if (isset($names[$processor->name])) {
                throw InvalidPrivacyValue::because(\sprintf('The processor "%s" is listed twice.', $processor->name));
            }

            $names[$processor->name] = $processor;
        }

        $this->recipients = $recipients;
        $this->processors = $processors;
    }

    /**
     * Whether the register can answer the two questions the law always asks of a store: why, and where.
     */
    public function isComplete(): bool
    {
        return null !== $this->purpose && null !== $this->location;
    }

    private function line(string $field, string $value): void
    {
        if ('' === trim($value) || mb_strlen($value) > self::MAX_LINE || 1 === preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw InvalidPrivacyValue::because(\sprintf('A %s is one line of at most %d characters.', $field, self::MAX_LINE));
        }
    }
}
