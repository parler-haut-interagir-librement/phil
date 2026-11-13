<?php

declare(strict_types=1);

namespace Phil\IncidentMode;

use Phil\IncidentMode\Exception\InvalidIncident;

/**
 * A named set of restrictions, with the duration an activation proposes and the longest it may be given.
 *
 * A preset has no activation without an end: both durations are required, and the proposed one is within the
 * longest.
 */
final readonly class Preset
{
    public const int MAX_LABEL_LENGTH = 120;

    /**
     * @param non-empty-list<Restriction> $restrictions
     */
    private function __construct(
        public string $name,
        public string $label,
        public array $restrictions,
        public Duration $defaultDuration,
        public Duration $maximumDuration,
    ) {
    }

    /**
     * @param list<string> $restrictions the names of the restrictions, at least one; a repeated name counts once
     */
    public static function of(string $name, string $label, array $restrictions, Duration $defaultDuration, Duration $maximumDuration): self
    {
        if (1 !== preg_match(Restriction::PATTERN, $name)) {
            throw InvalidIncident::because(\sprintf('"%s" is not a preset name: a lowercase letter, then up to 63 lowercase letters, digits and hyphens.', $name));
        }

        $label = trim($label);

        if ('' === $label || mb_strlen($label) > self::MAX_LABEL_LENGTH) {
            throw InvalidIncident::because(\sprintf('The label of the preset "%s" has 1 to %d characters.', $name, self::MAX_LABEL_LENGTH));
        }

        $unique = [];

        foreach ($restrictions as $restriction) {
            $unique[$restriction] = Restriction::named($restriction);
        }

        if ([] === $unique) {
            throw InvalidIncident::because(\sprintf('The preset "%s" suspends at least one thing.', $name));
        }

        if ($defaultDuration->isLongerThan($maximumDuration)) {
            throw InvalidIncident::because(\sprintf('The preset "%s" proposes a duration longer than the longest it allows.', $name));
        }

        return new self($name, $label, array_values($unique), $defaultDuration, $maximumDuration);
    }

    /**
     * @return non-empty-list<string>
     */
    public function restrictionNames(): array
    {
        return array_map(static fn (Restriction $restriction): string => $restriction->name, $this->restrictions);
    }
}
