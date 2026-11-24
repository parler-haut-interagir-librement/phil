<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceConsignmentBundle\Domain\Consignor\StatementFrequency;
use Phil\CommerceConsignmentBundle\Domain\Consignor\UnsoldPolicy;
use Phil\CommerceConsignmentBundle\Domain\Exception\InvalidValue;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Symfony\Component\Uid\Uuid;

/**
 * One version of what the shop agreed with a consignor: how long their units stay, what happens to the unsold ones then,
 * and how often they receive a statement. A version applies to the units received from its instant on, until the next
 * version; a new version never changes the units received before it, nor the terms each receipt line wrote.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_consignment_agreement')]
#[ORM\UniqueConstraint(name: 'phil_commerce_consignment_agreement_version', columns: ['consignor', 'effective_from'])]
final class ConsignmentAgreement
{
    /** The longest term, extension included, in days: ten years. */
    public const int MAX_DAYS = 3650;

    /** The most extensions an agreement grants. */
    public const int MAX_EXTENSIONS = 12;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'consignor', length: 64)]
        public readonly string $consignor,
        #[ORM\Column(name: 'effective_from', type: 'datetime_utc_immutable')]
        private readonly DateTimeImmutable $effectiveFrom,
        #[ORM\Column(name: 'term_days', type: 'integer', nullable: true)]
        public readonly ?int $termDays,
        #[ORM\Column(name: 'unsold_policy', length: 16, enumType: UnsoldPolicy::class)]
        public readonly UnsoldPolicy $unsoldPolicy,
        #[ORM\Column(name: 'extension_days', type: 'integer', nullable: true)]
        public readonly ?int $extensionDays,
        #[ORM\Column(name: 'extensions', type: 'integer')]
        public readonly int $extensions,
        #[ORM\Column(name: 'frequency', length: 16, enumType: StatementFrequency::class)]
        public readonly StatementFrequency $frequency,
        #[ORM\Column(name: 'recorded_at', type: 'datetime_utc_immutable')]
        private readonly DateTimeImmutable $recordedAt,
    ) {
    }

    /**
     * @param int|null $termDays      how many days the units stay before the policy applies; null for no end
     * @param int|null $extensionDays the days each extension adds, with `UnsoldPolicy::Extend` only
     * @param int      $extensions    how many times the consignment is extended, with `UnsoldPolicy::Extend` only
     */
    public static function version(
        Owner $consignor,
        DateTimeImmutable $effectiveFrom,
        ?int $termDays,
        UnsoldPolicy $unsoldPolicy,
        StatementFrequency $frequency,
        DateTimeImmutable $recordedAt,
        ?int $extensionDays = null,
        int $extensions = 1,
    ): self {
        if (!$consignor->isConsigned()) {
            throw InvalidValue::because('An agreement is made with a consignor, not with the shop.');
        }

        self::checkDays($termDays, 'A term');

        if (UnsoldPolicy::Extend === $unsoldPolicy) {
            if (null === $termDays || null === $extensionDays) {
                throw InvalidValue::because('An agreement that extends a consignment has a term and the days each extension adds.');
            }

            self::checkDays($extensionDays, 'An extension');

            if ($extensions < 1 || $extensions > self::MAX_EXTENSIONS || $termDays + $extensions * $extensionDays > self::MAX_DAYS) {
                throw InvalidValue::because(\sprintf('An agreement grants 1 to %d extensions, within %d days in all; %d of %d days do not fit.', self::MAX_EXTENSIONS, self::MAX_DAYS, $extensions, $extensionDays));
            }
        } elseif (null !== $extensionDays) {
            throw InvalidValue::because('Only an agreement that extends a consignment adds extension days.');
        }

        $utc = new DateTimeZone('UTC');

        return new self(
            Uuid::v7()->toRfc4122(),
            (string) $consignor->consignor,
            $effectiveFrom->setTimezone($utc),
            $termDays,
            $unsoldPolicy,
            UnsoldPolicy::Extend === $unsoldPolicy ? $extensionDays : null,
            UnsoldPolicy::Extend === $unsoldPolicy ? $extensions : 0,
            $frequency,
            $recordedAt->setTimezone($utc),
        );
    }

    public function effectiveFrom(): DateTimeImmutable
    {
        return $this->effectiveFrom;
    }

    public function recordedAt(): DateTimeImmutable
    {
        return $this->recordedAt;
    }

    /**
     * When units received at the instant are due to leave the consignment, every extension included; null for no end.
     */
    public function dueAt(DateTimeImmutable $receivedAt): ?DateTimeImmutable
    {
        if (null === $this->termDays) {
            return null;
        }

        return $receivedAt->setTimezone(new DateTimeZone('UTC'))->modify(\sprintf('+%d days', $this->termDays + (null === $this->extensionDays ? 0 : $this->extensions * $this->extensionDays)));
    }

    /**
     * What happens to the unsold units once due: given back, or bought by the shop. Extensions end with the units given
     * back.
     */
    public function actionWhenDue(): UnsoldPolicy
    {
        return UnsoldPolicy::BuyOut === $this->unsoldPolicy ? UnsoldPolicy::BuyOut : UnsoldPolicy::ReturnToConsignor;
    }

    private static function checkDays(?int $days, string $what): void
    {
        if (null !== $days && ($days < 1 || $days > self::MAX_DAYS)) {
            throw InvalidValue::because(\sprintf('%s lasts 1 to %d days; %d does not.', $what, self::MAX_DAYS, $days));
        }
    }
}
