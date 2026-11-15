<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;

/**
 * A channel that sells: a site of the application with its own domains, or a point of sale. Each channel has its
 * currency, its locale, its theme, the tax zone its orders start in, and — through the modules — its own selection
 * of what it sells, its prices and its promotions.
 *
 * A channel also says how long a placed order may wait for its payment before it expires — 24 hours by default —,
 * and how long an expired order is kept before it is deleted — 30 days by default —, so that a store keeps no more
 * than it needs of orders nobody paid; `null` turns either off.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_channel')]
final class Channel
{
    /** How many hours a placed order waits for its payment before it expires, by default. */
    public const int UNPAID_EXPIRY_HOURS = 24;

    /** How many days an expired order is kept before it is deleted, by default. */
    public const int EXPIRED_RETENTION_DAYS = 30;

    public Currency $currency {
        get => Currency::of($this->currencyCode);
    }

    /** @var list<string> */
    #[ORM\Column(name: 'hostnames', type: 'json')]
    private array $hostnames = [];

    #[ORM\Column(name: 'active')]
    private bool $active = true;

    #[ORM\Column(name: 'unpaid_expiry_hours', type: 'integer', nullable: true, options: ['default' => self::UNPAID_EXPIRY_HOURS])]
    private ?int $unpaidExpiryHours = self::UNPAID_EXPIRY_HOURS;

    #[ORM\Column(name: 'expired_retention_days', type: 'integer', nullable: true, options: ['default' => self::EXPIRED_RETENTION_DAYS])]
    private ?int $expiredRetentionDays = self::EXPIRED_RETENTION_DAYS;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'code', length: 64)]
        public readonly string $code,
        #[ORM\Column(name: 'name', length: 255)]
        private string $name,
        #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
        private readonly string $currencyCode,
        #[ORM\Column(name: 'locale', length: 16)]
        private string $locale,
        #[ORM\Column(name: 'theme', length: 64, nullable: true)]
        private ?string $theme,
        #[ORM\Column(name: 'tax_zone', length: 64, nullable: true)]
        private ?string $taxZone,
    ) {
    }

    /**
     * @param string      $locale  the locale its pages are written in, `fr_CA`
     * @param string|null $theme   the theme its pages are drawn with, as the application names it
     * @param string|null $taxZone the tax zone its orders start in, before the buyer's is known: a store's own
     */
    public static function create(string $code, string $name, Currency $currency, string $locale, ?string $theme = null, ?string $taxZone = null): self
    {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/D', $code)) {
            throw InvalidValue::because(\sprintf('"%s" is not a channel code: up to 64 lowercase letters, digits, "_", "." or "-".', $code));
        }

        return new self($code, self::checkedName($name), $currency->code, self::checkedLocale($locale), self::checkedOptional($theme, 'theme'), self::checkedOptional($taxZone, 'tax zone'));
    }

    public function name(): string
    {
        return $this->name;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function theme(): ?string
    {
        return $this->theme;
    }

    public function taxZone(): ?string
    {
        return $this->taxZone;
    }

    /**
     * @return list<string> the domains it answers on, lowercase
     */
    public function hostnames(): array
    {
        return $this->hostnames;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function rename(string $name): void
    {
        $this->name = self::checkedName($name);
    }

    public function present(string $locale, ?string $theme): void
    {
        $this->locale = self::checkedLocale($locale);
        $this->theme = self::checkedOptional($theme, 'theme');
    }

    public function startOrdersIn(?string $taxZone): void
    {
        $this->taxZone = self::checkedOptional($taxZone, 'tax zone');
    }

    /**
     * Answers on a domain too, written in any case; a point of sale has none.
     */
    public function answerOn(string $hostname): void
    {
        $hostname = strtolower($hostname);

        if (1 !== preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/D', $hostname)) {
            throw InvalidValue::because(\sprintf('"%s" is not a hostname.', $hostname));
        }

        if (!\in_array($hostname, $this->hostnames, true)) {
            $this->hostnames[] = $hostname;
        }
    }

    public function stopAnsweringOn(string $hostname): void
    {
        $this->hostnames = array_values(array_diff($this->hostnames, [strtolower($hostname)]));
    }

    public function answersOn(string $hostname): bool
    {
        return \in_array(strtolower($hostname), $this->hostnames, true);
    }

    /**
     * How many hours a placed order of the channel waits for its payment before it expires; `null` when it never does.
     */
    public function unpaidExpiryHours(): ?int
    {
        return $this->unpaidExpiryHours;
    }

    /**
     * How many days an expired order of the channel is kept before it is deleted; `null` when it is kept.
     */
    public function expiredRetentionDays(): ?int
    {
        return $this->expiredRetentionDays;
    }

    /**
     * @param int|null $hours 1 to 8,760 hours before an unpaid order expires, or `null` for never
     * @param int|null $days  1 to 3,650 days before an expired order is deleted, or `null` to keep it
     */
    public function expireUnpaidOrders(?int $hours, ?int $days): void
    {
        if ((null !== $hours && ($hours < 1 || $hours > 8_760)) || (null !== $days && ($days < 1 || $days > 3_650))) {
            throw InvalidValue::because(\sprintf('The channel "%s" expires unpaid orders after 1 to 8760 hours and deletes them after 1 to 3650 days, or never.', $this->code));
        }

        $this->unpaidExpiryHours = $hours;
        $this->expiredRetentionDays = $days;
    }

    /**
     * Sells no more, its orders kept.
     */
    public function close(): void
    {
        $this->active = false;
    }

    public function reopen(): void
    {
        $this->active = true;
    }

    private static function checkedName(string $name): string
    {
        if ('' === trim($name)) {
            throw InvalidValue::because('A channel has a name.');
        }

        return $name;
    }

    private static function checkedLocale(string $locale): string
    {
        if (1 !== preg_match('/^[a-z]{2,3}(_[A-Z][a-z]{3})?(_[A-Z]{2}|_\d{3})?$/D', $locale)) {
            throw InvalidValue::because(\sprintf('"%s" is not a locale: "fr", "fr_CA" or "sr_Latn_RS".', $locale));
        }

        return $locale;
    }

    private static function checkedOptional(?string $value, string $what): ?string
    {
        if (null !== $value && 1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $value)) {
            throw InvalidValue::because(\sprintf('"%s" is not a %s: 1 to 64 visible ASCII characters.', $value, $what));
        }

        return $value;
    }
}
