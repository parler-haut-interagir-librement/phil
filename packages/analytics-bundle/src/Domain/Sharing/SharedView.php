<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Sharing;

use DateTimeImmutable;
use Phil\AnalyticsBundle\Domain\Measure\MeasureNames;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The measures of a site opened to people without an account: by a share link, or as the site's public board.
 * Revoking closes it for good.
 *
 * What it shows goes through the small-number rule like any other read, and it never holds the metrics of
 * {@see MeasureNames::PRIVATE_METRICS}.
 */
final readonly class SharedView
{
    /**
     * @param string|null $tokenDigest {@see ShareToken::digest()} of a share link, `null` for the public board
     */
    public function __construct(
        public SiteId $site,
        public ?string $tokenDigest,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $expiresAt = null,
        public ?DateTimeImmutable $revokedAt = null,
    ) {
        if ($expiresAt instanceof DateTimeImmutable && $expiresAt <= $createdAt) {
            throw InvalidValue::because('A shared view expires after it is created.');
        }
    }

    public static function link(SiteId $site, ShareToken $token, DateTimeImmutable $createdAt, ?DateTimeImmutable $expiresAt = null): self
    {
        return new self($site, $token->digest(), $createdAt, $expiresAt);
    }

    public static function publicBoard(SiteId $site, DateTimeImmutable $createdAt): self
    {
        return new self($site, null, $createdAt);
    }

    public function revoked(DateTimeImmutable $at): self
    {
        return new self($this->site, $this->tokenDigest, $this->createdAt, $this->expiresAt, $this->revokedAt ?? $at);
    }

    public function isOpenAt(DateTimeImmutable $now): bool
    {
        return !$this->revokedAt instanceof DateTimeImmutable && (!$this->expiresAt instanceof DateTimeImmutable || $now < $this->expiresAt);
    }

    public function mayShow(string $metric): bool
    {
        return !\in_array($metric, MeasureNames::PRIVATE_METRICS, true);
    }
}
