<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\ValueObject;

/**
 * The privacy signals a browser sends: Do Not Track (`DNT: 1`) and Global Privacy Control (`Sec-GPC: 1`).
 *
 * **A browser that sends either is not counted**, in any counter. Only the value `1` is a signal: `DNT: 0`
 * expresses consent to nothing, and an absent header is no signal. `Sec-GPC` may be repeated or carry a list separated
 * by commas, all its occurrences joined by `CollectController`: it is a signal if one of its trimmed values is `1`.
 */
final readonly class PrivacySignals
{
    private function __construct(
        public bool $doNotTrack,
        public bool $globalPrivacyControl,
    ) {
    }

    public static function fromHeaders(?string $dnt, ?string $secGpc): self
    {
        return new self('1' === trim($dnt ?? ''), self::listsOne($secGpc));
    }

    /**
     * Whether one of the comma-separated values of a header, trimmed, is `1`.
     */
    private static function listsOne(?string $header): bool
    {
        return array_any(explode(',', $header ?? ''), static fn (string $value): bool => '1' === trim($value));
    }

    public static function none(): self
    {
        return new self(false, false);
    }

    public function optOut(): bool
    {
        return $this->doNotTrack || $this->globalPrivacyControl;
    }
}
