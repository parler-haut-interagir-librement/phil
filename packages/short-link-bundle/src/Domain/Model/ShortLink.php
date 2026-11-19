<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\RedirectsBundle\Domain\Enum\QueryPolicy;
use Phil\RedirectsBundle\Domain\Enum\RedirectStatus;
use Phil\ShortLinkBundle\Domain\Enum\LinkState;
use Phil\ShortLinkBundle\Domain\Exception\InvalidShortLink;
use Phil\ShortLinkBundle\Domain\ValueObject\LinkTerms;

/**
 * A short code that sends to a target URL, on the domain of its locale or on every domain when it has none: in force
 * from a moment, until a moment, for a number of visits, each optional, and while enabled.
 *
 * Its code and its target are checked by the application before they reach the link; the link holds the rules that
 * concern it alone: a redirection status (a `410 Gone` is what a link answers once it is no longer in force, never a
 * status it is given), an end after its start, a maximum of one visit or more, a label of 200 characters at most.
 * Moments are kept in UTC by the type of their columns, `datetime_utc_immutable`. It keeps a count of the visits of
 * people and the moment of the last one, which its maximum reads, and apart a count of the visits of machines —
 * robots, prefetches, link previews —; nothing of who visited.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\UniqueConstraint(name: 'phil_short_link_code', columns: ['code'])]
#[ORM\Index(name: 'phil_short_link_created', columns: ['created_at'])]
final class ShortLink
{
    public const string TABLE = 'phil_short_link';

    public const int MAX_CODE_LENGTH = 64;

    public const int MAX_TARGET_LENGTH = 2048;

    public const int MAX_LABEL_LENGTH = 200;

    private const string LOCALE = '/^[a-z]{2,3}(?:[_-][A-Za-z0-9]{2,8}){0,3}$/D';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id')]
    private ?int $id = null;

    #[ORM\Column(name: 'target', length: self::MAX_TARGET_LENGTH)]
    private string $target;

    #[ORM\Column(name: 'label', length: self::MAX_LABEL_LENGTH, nullable: true)]
    private ?string $label;

    #[ORM\Column(name: 'locale', length: 35, nullable: true)]
    private ?string $locale;

    #[ORM\Column(name: 'status', type: 'smallint', enumType: RedirectStatus::class)]
    private RedirectStatus $status;

    #[ORM\Column(name: 'query_policy', length: 8, enumType: QueryPolicy::class)]
    private QueryPolicy $query;

    #[ORM\Column(name: 'enabled')]
    private bool $enabled;

    #[ORM\Column(name: 'valid_from', type: 'datetime_utc_immutable', nullable: true)]
    private ?DateTimeImmutable $validFrom;

    #[ORM\Column(name: 'valid_until', type: 'datetime_utc_immutable', nullable: true)]
    private ?DateTimeImmutable $validUntil;

    #[ORM\Column(name: 'max_visits', nullable: true)]
    private ?int $maxVisits;

    #[ORM\Column(name: 'visits')]
    private int $visits = 0;

    #[ORM\Column(name: 'robot_visits', options: ['default' => 0])]
    private int $robotVisits = 0;

    #[ORM\Column(name: 'last_visit_at', type: 'datetime_utc_immutable', nullable: true)]
    private ?DateTimeImmutable $lastVisitAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime_utc_immutable')]
    private DateTimeImmutable $updatedAt;

    /**
     * @throws InvalidShortLink
     */
    public function __construct(
        #[ORM\Column(name: 'code', length: self::MAX_CODE_LENGTH)]
        private string $code,
        string $target,
        #[ORM\Column(name: 'created_at', type: 'datetime_utc_immutable')]
        private DateTimeImmutable $createdAt,
        LinkTerms $terms = new LinkTerms(),
    ) {
        if ('' === $code || \strlen($code) > self::MAX_CODE_LENGTH) {
            throw InvalidShortLink::because('code_invalid', 'A code has between 1 and %max% characters.', ['%max%' => (string) self::MAX_CODE_LENGTH]);
        }

        $this->change($target, $terms, $createdAt);
    }

    /**
     * Changes the target and the terms; the code, the visits counted and the creation stay.
     *
     * @throws InvalidShortLink
     */
    public function change(string $target, LinkTerms $terms, DateTimeImmutable $now): void
    {
        if ('' === $target || \strlen($target) > self::MAX_TARGET_LENGTH) {
            throw InvalidShortLink::because('target', 'A target has between 1 and %max% characters.', ['%max%' => (string) self::MAX_TARGET_LENGTH]);
        }

        if ($terms->status->isGone()) {
            throw InvalidShortLink::because('status', 'A short link redirects: 410 Gone is not a status it is given.');
        }

        if ($terms->validFrom instanceof DateTimeImmutable && $terms->validUntil instanceof DateTimeImmutable && $terms->validUntil <= $terms->validFrom) {
            throw InvalidShortLink::because('dates', 'The end of a short link comes after its start.');
        }

        if (null !== $terms->maxVisits && $terms->maxVisits < 1) {
            throw InvalidShortLink::because('max_visits', 'A maximum number of visits is 1 or more.');
        }

        $label = null === $terms->label ? null : trim($terms->label);

        if (null !== $label && mb_strlen($label) > self::MAX_LABEL_LENGTH) {
            throw InvalidShortLink::because('label', 'A label has %max% characters at most.', ['%max%' => (string) self::MAX_LABEL_LENGTH]);
        }

        if (null !== $terms->locale && 1 !== preg_match(self::LOCALE, $terms->locale)) {
            throw InvalidShortLink::because('locale', 'The locale "%locale%" is not a locale.', ['%locale%' => $terms->locale]);
        }

        $this->target = $target;
        $this->label = '' === $label ? null : $label;
        $this->locale = $terms->locale;
        $this->status = $terms->status;
        $this->query = $terms->query;
        $this->enabled = $terms->enabled;
        $this->validFrom = $terms->validFrom;
        $this->validUntil = $terms->validUntil;
        $this->maxVisits = $terms->maxVisits;
        $this->updatedAt = $now;
    }

    public function enable(bool $enabled, DateTimeImmutable $now): void
    {
        $this->enabled = $enabled;
        $this->updatedAt = $now;
    }

    /**
     * Where the link stands at a moment: disabled first, then past its end, out of visits, before its start.
     */
    public function stateAt(DateTimeImmutable $now): LinkState
    {
        return match (true) {
            !$this->enabled => LinkState::Disabled,
            $this->validUntil instanceof DateTimeImmutable && $now >= $this->validUntil => LinkState::Expired,
            null !== $this->maxVisits && $this->visits >= $this->maxVisits => LinkState::Exhausted,
            $this->validFrom instanceof DateTimeImmutable && $now < $this->validFrom => LinkState::Scheduled,
            default => LinkState::Active,
        };
    }

    /**
     * Whether a domain of that locale answers for the link: every domain for a link without a locale, a domain of its
     * locale or one without a locale otherwise.
     */
    public function isServedIn(?string $domainLocale): bool
    {
        return null === $this->locale || null === $domainLocale || $this->locale === $domainLocale;
    }

    /**
     * The visits still allowed, none when the link has no maximum.
     */
    public function remainingVisits(): ?int
    {
        return null === $this->maxVisits ? null : max(0, $this->maxVisits - $this->visits);
    }

    public function terms(): LinkTerms
    {
        return new LinkTerms($this->status, $this->query, $this->label, $this->locale, $this->enabled, $this->validFrom, $this->validUntil, $this->maxVisits);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function target(): string
    {
        return $this->target;
    }

    public function label(): ?string
    {
        return $this->label;
    }

    public function locale(): ?string
    {
        return $this->locale;
    }

    public function status(): RedirectStatus
    {
        return $this->status;
    }

    public function query(): QueryPolicy
    {
        return $this->query;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function validFrom(): ?DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function validUntil(): ?DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function maxVisits(): ?int
    {
        return $this->maxVisits;
    }

    /**
     * The visits of people, which the maximum counts.
     */
    public function visits(): int
    {
        return $this->visits;
    }

    /**
     * The visits of machines — robots, prefetches, link previews —, which never count towards the maximum.
     */
    public function robotVisits(): int
    {
        return $this->robotVisits;
    }

    public function lastVisitAt(): ?DateTimeImmutable
    {
        return $this->lastVisitAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
