<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceSubscriptionBundle\Domain\Exception\GiftRefused;
use Phil\CommerceSubscriptionBundle\Domain\Gift\GiftRenewal;
use Phil\CommerceSubscriptionBundle\Domain\Gift\GiftState;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\StableIdentifier;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * A subscription offered to someone else: the buyer pays one or more periods of an offer by an order of the core, and
 * a code — typed, or carried by a link — starts the subscription of whoever redeems it.
 *
 * - **The code is shown once**, when it is issued; only its SHA-256 is stored, and a lost code is replaced by a new one,
 *   which voids the old.
 * - **The beneficiary is named by a stable identifier** when they redeem it, never by an email address.
 * - **Once the periods paid are over**, the subscription renews at the buyer's charge, or ends at its term.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_subscription_gift')]
#[ORM\Index(columns: ['buyer'])]
#[ORM\UniqueConstraint(columns: ['code_hash'])]
final class Gift
{
    /** Crockford's base 32: no I, L, O or U, which read as other characters. */
    private const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /** 16 characters of 5 bits: 80 bits, out of reach of guessing. */
    private const int CODE_LENGTH = 16;

    #[ORM\Column(name: 'state', length: 16, enumType: GiftState::class)]
    private GiftState $state = GiftState::Pending;

    #[ORM\Column(name: 'code_hash', length: 64, nullable: true, options: ['fixed' => true])]
    private ?string $codeHash = null;

    #[ORM\Column(name: 'awaited_order', length: 64, nullable: true)]
    private ?string $awaitedOrder = null;

    /** The price of one period, as the buyer paid it. */
    #[ORM\Column(name: 'period_amount', type: 'bigint')]
    private int $periodAmount = 0;

    #[ORM\Column(name: 'redeemable_until', nullable: true)]
    private ?DateTimeImmutable $redeemableUntil = null;

    #[ORM\Column(name: 'beneficiary', length: 255, nullable: true)]
    private ?string $beneficiary = null;

    #[ORM\Column(name: 'subscription', length: 36, nullable: true, options: ['fixed' => true])]
    private ?string $subscriptionId = null;

    #[ORM\Column(name: 'closed_at', nullable: true)]
    private ?DateTimeImmutable $closedAt = null;

    #[ORM\Version]
    #[ORM\Column(name: 'version', type: 'integer')]
    private int $version = 1;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'buyer', length: 255)]
        public readonly string $buyer,
        #[ORM\Column(name: 'offer', length: 64)]
        public readonly string $offerCode,
        #[ORM\Column(name: 'periods', type: 'smallint')]
        public readonly int $periods,
        #[ORM\Column(name: 'renewal', length: 16, enumType: GiftRenewal::class)]
        public readonly GiftRenewal $renewal,
        #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
        private string $currencyCode,
        #[ORM\Column(name: 'created_at')]
        public readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * A gift of `$periods` periods of a paid offer on sale, waiting for its order.
     */
    public static function buy(Offer $offer, StableIdentifier $buyer, int $periods, GiftRenewal $renewal, DateTimeImmutable $at): self
    {
        if ($periods < 1 || $periods > 36) {
            throw InvalidValue::because(\sprintf('A gift pays 1 to 36 periods; %d does not.', $periods));
        }

        if (!$offer->isActive()) {
            throw GiftRefused::withdrawn($offer->code);
        }

        if ($offer->isFree()) {
            throw GiftRefused::free($offer->code);
        }

        return new self(Uuid::v7()->toRfc4122(), $buyer->value, $offer->code, $periods, $renewal, $offer->price->currency->code, $at);
    }

    /**
     * The SHA-256 of a code as typed — any case, with or without dashes and spaces, `O` read as `0`, `I` and `L` as
     * `1` —, or `null` when it cannot be a code.
     */
    public static function hashOf(string $code): ?string
    {
        if (\strlen($code) > 64) {
            return null;
        }

        $normalized = strtr(strtoupper(str_replace(['-', ' '], '', $code)), ['O' => '0', 'I' => '1', 'L' => '1']);

        return 1 === preg_match('/^[0-9A-HJKMNP-TV-Z]{16}$/D', $normalized) ? hash('sha256', $normalized) : null;
    }

    public function state(): GiftState
    {
        return $this->state;
    }

    public function currency(): Currency
    {
        return Currency::of($this->currencyCode);
    }

    public function periodAmount(): Money
    {
        return Money::of($this->periodAmount, $this->currency());
    }

    /**
     * The SHA-256 of the current code, which a store indexes; `null` before the gift is paid and once it is closed.
     */
    public function codeHash(): ?string
    {
        return $this->codeHash;
    }

    public function awaitedOrder(): ?string
    {
        return $this->awaitedOrder;
    }

    public function redeemableUntil(): ?DateTimeImmutable
    {
        return $this->redeemableUntil;
    }

    public function beneficiary(): ?string
    {
        return $this->beneficiary;
    }

    public function subscriptionId(): ?string
    {
        return $this->subscriptionId;
    }

    public function closedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function version(): int
    {
        return $this->version;
    }

    /**
     * Whether its order may be placed: pending, and no order awaited yet.
     */
    public function awaitsAnOrder(): bool
    {
        return GiftState::Pending === $this->state && null === $this->awaitedOrder;
    }

    /**
     * The order of the gift was placed; the gift awaits its payment.
     *
     * @param Money $periodAmount the price of one period on the order
     */
    public function awaitPayment(string $orderNumber, Money $periodAmount): void
    {
        if (!$this->awaitsAnOrder()) {
            throw GiftRefused::state($this->id, $this->state, 'await the payment of another order');
        }

        if (!$periodAmount->currency->equals($this->currency()) || $periodAmount->isNegative()) {
            throw InvalidValue::because(\sprintf('The gift %s is paid in %s, at zero or more.', $this->id, $this->currencyCode));
        }

        $this->awaitedOrder = $orderNumber;
        $this->periodAmount = $periodAmount->minorUnits;
    }

    /**
     * The order was paid: the gift may be redeemed until `$redeemableUntil`, with the code returned — shown once.
     */
    public function paid(string $orderNumber, DateTimeImmutable $redeemableUntil): string
    {
        $this->checkAwaited($orderNumber);
        $this->awaitedOrder = null;
        $this->state = GiftState::Paid;
        $this->redeemableUntil = $redeemableUntil;

        return $this->issueCode();
    }

    /**
     * The payment of the order failed: the gift waits for another order.
     */
    public function paymentFailed(string $orderNumber): void
    {
        $this->checkAwaited($orderNumber);
        $this->awaitedOrder = null;
    }

    /**
     * A new code for a paid gift whose code was lost; the old one no longer redeems it.
     */
    public function reissueCode(): string
    {
        if (GiftState::Paid !== $this->state) {
            throw GiftRefused::state($this->id, $this->state, 'have its code replaced');
        }

        return $this->issueCode();
    }

    /**
     * Whether the code redeems the gift now: paid, the code its own, and not expired.
     */
    public function isRedeemableWith(string $code, DateTimeImmutable $at): bool
    {
        $hash = self::hashOf($code);

        return GiftState::Paid === $this->state && null !== $hash && null !== $this->codeHash && hash_equals($this->codeHash, $hash) && $at < $this->redeemableUntil;
    }

    /**
     * The beneficiary redeemed it with its code: the subscription it started is recorded, and the code voided.
     */
    public function redeem(string $code, StableIdentifier $beneficiary, Subscription $subscription, DateTimeImmutable $at): void
    {
        if (!$this->isRedeemableWith($code, $at)) {
            throw GiftRefused::notRedeemable();
        }

        $this->state = GiftState::Redeemed;
        $this->beneficiary = $beneficiary->value;
        $this->subscriptionId = $subscription->id;
        $this->codeHash = null;
        $this->closedAt = $at;
    }

    /**
     * Gives the gift up before it is redeemed, or once it expired: its code no longer redeems it. Refunding what was
     * paid is the payment's affair.
     */
    public function cancel(DateTimeImmutable $at): void
    {
        if (GiftState::Redeemed === $this->state || GiftState::Cancelled === $this->state || null !== $this->awaitedOrder) {
            throw GiftRefused::state($this->id, $this->state, 'be cancelled while its order awaits a payment, or once redeemed or cancelled');
        }

        $this->state = GiftState::Cancelled;
        $this->codeHash = null;
        $this->closedAt = $at;
    }

    private function issueCode(): string
    {
        $code = '';

        for ($index = 0; $index < self::CODE_LENGTH; ++$index) {
            $code .= self::ALPHABET[random_int(0, 31)];
        }

        $this->codeHash = hash('sha256', $code);

        return implode('-', str_split($code, 4));
    }

    private function checkAwaited(string $orderNumber): void
    {
        if ($orderNumber !== $this->awaitedOrder) {
            throw GiftRefused::notTheOrder($this->id, $orderNumber);
        }
    }
}
