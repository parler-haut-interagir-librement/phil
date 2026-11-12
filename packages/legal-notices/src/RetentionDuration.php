<?php

declare(strict_types=1);

namespace Phil\LegalNotices;

use Phil\LegalNotices\Exception\InvalidLegalNotice;

/**
 * How long a site keeps one kind of personal information, as its privacy policy says it: an identifier the body cites
 * as `${retention.<id>}`, what is kept — in a default wording and maybe in each locale —, and the period. A fixed period
 * is an ISO 8601 duration such as `P90D`; a period until an event names the event; a period with no set end or not set
 * yet carries no duration.
 */
final readonly class RetentionDuration
{
    public const string ID_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/D';

    public const int MAX_LABEL_BYTES = 300;

    /**
     * @var array<string, non-empty-string> what is kept, by locale
     */
    public array $labels;

    /**
     * @param array<array-key, mixed> $labels by locale
     *
     * @throws InvalidLegalNotice
     */
    public function __construct(
        public string $id,
        public string $label,
        public RetentionKind $kind,
        public ?string $duration = null,
        public ?string $note = null,
        array $labels = [],
    ) {
        if (1 !== preg_match(self::ID_PATTERN, $id)) {
            throw InvalidLegalNotice::because('retention_id', \sprintf('%s is not a retention identifier: letters, digits, ".", "_" and "-", 64 characters at most.', InvalidLegalNotice::quote($id)));
        }

        if ('' === trim($label) || \strlen($label) > self::MAX_LABEL_BYTES) {
            throw InvalidLegalNotice::because('retention_label', \sprintf('Retention %s says what it keeps in 1 to 300 bytes.', $id));
        }

        if (RetentionKind::Fixed === $kind) {
            DurationWords::parts($duration ?? '');
        } elseif (null !== $duration) {
            throw InvalidLegalNotice::because('retention_duration', \sprintf('Retention %s is not fixed: it has no duration.', $id));
        }

        if (RetentionKind::UntilEvent === $kind && null === $note) {
            throw InvalidLegalNotice::because('retention_event', \sprintf('Retention %s runs until an event, which it names.', $id));
        }

        $this->labels = Locales::texts($labels, self::MAX_LABEL_BYTES, 'retention label');
    }

    /**
     * What is kept, in a locale or its language, else in the default wording.
     */
    public function label(string $locale): string
    {
        return Locales::find($this->labels, $locale) ?? $this->label;
    }
}
