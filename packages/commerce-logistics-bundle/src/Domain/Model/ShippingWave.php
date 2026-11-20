<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceLogisticsBundle\Domain\Exception\StockRefused;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * Orders planned to ship together on an estimated day — a preorder's goods arriving, a print run —, released when the
 * day comes and the goods are in.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_logistics_wave')]
#[ORM\Index(columns: ['released_at', 'estimated_on'])]
final class ShippingWave
{
    #[ORM\Column(name: 'released_at', nullable: true)]
    private ?DateTimeImmutable $releasedAt = null;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'code', length: 64)]
        public readonly string $code,
        #[ORM\Column(name: 'name', length: 255)]
        public readonly string $name,
        #[ORM\Column(name: 'estimated_on', type: 'date_immutable')]
        private DateTimeImmutable $estimatedOn,
    ) {
    }

    public static function plan(string $code, string $name, DateTimeImmutable $estimatedOn): self
    {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/D', $code)) {
            throw InvalidValue::because(\sprintf('"%s" is not a wave code: up to 64 lowercase letters, digits, "_", "." or "-".', $code));
        }

        if ('' === trim($name) || mb_strlen($name) > 255) {
            throw InvalidValue::because(\sprintf('The wave %s has a name of 1 to 255 characters.', $code));
        }

        return new self($code, $name, $estimatedOn->setTime(0, 0));
    }

    public function estimatedOn(): DateTimeImmutable
    {
        return $this->estimatedOn;
    }

    public function releasedAt(): ?DateTimeImmutable
    {
        return $this->releasedAt;
    }

    public function isReleased(): bool
    {
        return $this->releasedAt instanceof DateTimeImmutable;
    }

    public function reschedule(DateTimeImmutable $estimatedOn): void
    {
        $this->checkPlanned();
        $this->estimatedOn = $estimatedOn->setTime(0, 0);
    }

    public function release(DateTimeImmutable $at): void
    {
        $this->checkPlanned();
        $this->releasedAt = $at;
    }

    private function checkPlanned(): void
    {
        if ($this->isReleased()) {
            throw StockRefused::waveReleased($this->code);
        }
    }
}
