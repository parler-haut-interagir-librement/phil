<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * A convention the shop sells at, from its first day to its last: the stock prepared for it is entered per SKU — one
 * per size of a printed shirt — with `ConventionQuantity`.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_logistics_convention')]
#[ORM\Index(columns: ['ends_on'])]
final class Convention
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'code', length: 64)]
        public readonly string $code,
        #[ORM\Column(name: 'name', length: 255)]
        public readonly string $name,
        #[ORM\Column(name: 'starts_on', type: 'date_immutable')]
        private DateTimeImmutable $startsOn,
        #[ORM\Column(name: 'ends_on', type: 'date_immutable')]
        private DateTimeImmutable $endsOn,
    ) {
    }

    /**
     * @param DateTimeImmutable $startsOn its first day; the time of day is dropped
     * @param DateTimeImmutable $endsOn   its last day, the first one or later
     */
    public static function of(string $code, string $name, DateTimeImmutable $startsOn, DateTimeImmutable $endsOn): self
    {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/D', $code)) {
            throw InvalidValue::because(\sprintf('"%s" is not a convention code: up to 64 lowercase letters, digits, "_", "." or "-".', $code));
        }

        if ('' === trim($name) || mb_strlen($name) > 255) {
            throw InvalidValue::because(\sprintf('The convention %s has a name of 1 to 255 characters.', $code));
        }

        $convention = new self($code, $name, $startsOn, $endsOn);
        $convention->reschedule($startsOn, $endsOn);

        return $convention;
    }

    public function startsOn(): DateTimeImmutable
    {
        return $this->startsOn;
    }

    public function endsOn(): DateTimeImmutable
    {
        return $this->endsOn;
    }

    public function reschedule(DateTimeImmutable $startsOn, DateTimeImmutable $endsOn): void
    {
        $startsOn = $startsOn->setTime(0, 0);
        $endsOn = $endsOn->setTime(0, 0);

        if ($endsOn < $startsOn) {
            throw InvalidValue::because(\sprintf('The convention %s ends on its first day or later.', $this->code));
        }

        $this->startsOn = $startsOn;
        $this->endsOn = $endsOn;
    }

    /**
     * Whether any of its days falls between the two days, both included.
     */
    public function meets(DateTimeImmutable $from, DateTimeImmutable $until): bool
    {
        return $this->startsOn <= $until && $this->endsOn->modify('+1 day') > $from;
    }
}
