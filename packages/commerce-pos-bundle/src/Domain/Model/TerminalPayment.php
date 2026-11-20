<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * A payment of the core presented on a terminal: the terminal and the session it was taken in, and the processor's
 * own reference of the request, by which the adapter cancels or refunds it. The session counts the payment once the
 * processor's notification says it was captured.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_pos_terminal_payment')]
final class TerminalPayment
{
    #[ORM\Column(name: 'counted')]
    private bool $counted = false;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'payment', length: 36, options: ['fixed' => true])]
        public readonly string $payment,
        #[ORM\Column(name: 'terminal', length: 32)]
        public readonly string $terminal,
        #[ORM\Column(name: 'session', length: 36, options: ['fixed' => true])]
        public readonly string $session,
        #[ORM\Column(name: 'processor_reference', length: 255)]
        public readonly string $processorReference,
        #[ORM\Column(name: 'presented_at', type: 'datetime_utc_immutable')]
        public readonly DateTimeImmutable $presentedAt,
    ) {
    }

    public static function of(string $payment, Terminal $terminal, CashSession $session, string $processorReference, DateTimeImmutable $at): self
    {
        if (1 !== preg_match('/^[\x21-\x7E]{1,255}$/D', $processorReference)) {
            throw InvalidValue::because("A processor's reference is 1 to 255 visible ASCII characters.");
        }

        return new self($payment, $terminal->code, $session->id, $processorReference, $at);
    }

    public function isCounted(): bool
    {
        return $this->counted;
    }

    /**
     * Marks the payment counted in its session; once only.
     *
     * @return bool whether it was counted now
     */
    public function count(): bool
    {
        if ($this->counted) {
            return false;
        }

        $this->counted = true;

        return true;
    }
}
