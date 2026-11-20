<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceStoredValueBundle\Domain\Exception\StoredValueRefused;
use Phil\CommerceStoredValueBundle\Domain\Instrument\GiftCardOrigin;
use Phil\CommerceStoredValueBundle\Domain\Instrument\MovementKind;
use Phil\CommerceStoredValueBundle\Domain\Model\Instrument;
use Phil\CommerceStoredValueBundle\Domain\Repository\InstrumentRepository;
use Phil\CommerceStoredValueBundle\Domain\ValueObject\GiftCardCode;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Money;

/**
 * Gift cards: issued — sold, or given —, loaded, looked up by their code, voided. A given card may expire; a paid one
 * only when it is for a specific service. Each shop checks the law where it sells before it sets an expiry.
 */
final readonly class GiftCards
{
    public function __construct(
        private InstrumentRepository $instruments,
        private Payments $payments,
    ) {
    }

    /**
     * Issues a card paid for — sold at the counter, or by an order —, or, with `GiftCardOrigin::Given`, given.
     *
     * @throws InvalidValue when a paid card that is not for a specific service is given an expiry
     */
    public function issue(Money $value, DateTimeImmutable $at, ?DateTimeImmutable $expiresAt = null, ?string $orderNumber = null, GiftCardOrigin $origin = GiftCardOrigin::Paid, bool $specificService = false): IssuedGiftCard
    {
        $code = GiftCardCode::generate();
        [$card, $movement] = Instrument::giftCard($code, $value, $at, $expiresAt, $orderNumber, $origin, $specificService);
        $this->instruments->addInstrument($card);
        $this->instruments->addMovement($movement);

        return new IssuedGiftCard($card, $code);
    }

    /**
     * Gives a card, with nothing paid for it — a promotion, a gesture, a prize —, which may expire.
     */
    public function give(Money $value, DateTimeImmutable $at, ?DateTimeImmutable $expiresAt = null): IssuedGiftCard
    {
        return $this->issue($value, $at, $expiresAt, null, GiftCardOrigin::Given);
    }

    /**
     * Issues the gift cards a placed and fully paid order sold, one per unit of each `gift_card` line, at its face
     * value, as paid cards without expiry. **Issuing twice changes nothing**: an order's cards already issued are not issued again.
     *
     * @return list<IssuedGiftCard> the cards issued now
     *
     * @throws InvalidValue       when the order is not placed
     * @throws StoredValueRefused when something of the order is still to pay
     */
    public function issueFor(Order $order, DateTimeImmutable $at): array
    {
        if (OrderState::Placed !== $order->state()) {
            throw InvalidValue::because(\sprintf('The order %s is not placed: its gift cards are not issued.', $order->number));
        }

        $payable = $this->payments->summary($order)->payable();

        if ($payable->isPositive()) {
            throw StoredValueRefused::notPaid($order->number, $payable->toDecimal());
        }

        $values = [];

        foreach ($order->lines() as $line) {
            $value = GiftCardSellables::valueOf($line->reference);

            if ($value instanceof Money) {
                for ($unit = 0; $unit < $line->quantity; ++$unit) {
                    $values[] = $value;
                }
            }
        }

        $issued = [];

        foreach (\array_slice($values, $this->instruments->issuedFor($order->number)) as $value) {
            $issued[] = $this->issue($value, $at, null, $order->number);
        }

        return $issued;
    }

    public function load(string $code, Money $amount, DateTimeImmutable $at, ?string $note = null): Instrument
    {
        $card = $this->find($code);
        $this->instruments->addMovement($card->credit(MovementKind::Loaded, $amount, $at, null, null, $note));

        return $card;
    }

    public function void(string $code, DateTimeImmutable $at, ?string $note = null): Instrument
    {
        $card = $this->find($code);
        $this->instruments->addMovement($card->void($at, $note));

        return $card;
    }

    /**
     * Voids the cards an order issued that are still active: the order was cancelled. What they held is taken away,
     * each in a `Voided` movement; what they already spent is gone, and the application deducts it from what it
     * refunds of the order.
     *
     * @return list<Instrument> the cards voided now
     */
    public function voidFor(string $orderNumber, DateTimeImmutable $at, ?string $note = null): array
    {
        $voided = [];

        foreach ($this->instruments->cardsIssuedFor($orderNumber) as $card) {
            if ($card->isActive()) {
                $this->instruments->addMovement($card->void($at, $note));
                $voided[] = $card;
            }
        }

        return $voided;
    }

    /**
     * The card a code opens, whatever its state.
     *
     * @throws StoredValueRefused when no card has that code
     * @throws InvalidValue       when the code is not one
     */
    public function find(string $code): Instrument
    {
        return $this->instruments->findByCodeHash(GiftCardCode::fromString($code)->hash()) ?? throw StoredValueRefused::unknownCard();
    }
}
