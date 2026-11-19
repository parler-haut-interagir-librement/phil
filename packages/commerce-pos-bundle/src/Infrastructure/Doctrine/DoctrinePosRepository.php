<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommercePosBundle\Domain\Layaway\LayawayState;
use Phil\CommercePosBundle\Domain\Model\CashierPin;
use Phil\CommercePosBundle\Domain\Model\CashMovement;
use Phil\CommercePosBundle\Domain\Model\CashSession;
use Phil\CommercePosBundle\Domain\Model\HeldSale;
use Phil\CommercePosBundle\Domain\Model\ImportedSale;
use Phil\CommercePosBundle\Domain\Model\Layaway;
use Phil\CommercePosBundle\Domain\Model\Register;
use Phil\CommercePosBundle\Domain\Model\RegisterSale;
use Phil\CommercePosBundle\Domain\Model\SalePreset;
use Phil\CommercePosBundle\Domain\Model\SessionPayment;
use Phil\CommercePosBundle\Domain\Model\Terminal;
use Phil\CommercePosBundle\Domain\Model\TerminalPayment;
use Phil\CommercePosBundle\Domain\Register\RegisterRole;
use Phil\CommercePosBundle\Domain\Repository\CashSessionRepository;
use Phil\CommercePosBundle\Domain\Repository\RegisterRepository;
use Phil\CommercePosBundle\Domain\Repository\RegisterSaleRepository;
use Phil\CommercePosBundle\Domain\Session\SessionState;
use SortDirection;

/**
 * The register's storage through Doctrine: registers, their terminals, sale presets and cashiers' PINs, sessions and
 * their cash, sales, payments, held sales, layaways and imported sales.
 */
final readonly class DoctrinePosRepository implements RegisterRepository, CashSessionRepository, RegisterSaleRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function findRegister(string $code): ?Register
    {
        return $this->entityManager->find(Register::class, $code);
    }

    #[Override]
    public function addRegister(Register $register): void
    {
        $this->entityManager->persist($register);
    }

    #[Override]
    public function mainRegisterAt(?string $location): ?Register
    {
        return $this->entityManager->getRepository(Register::class)->findOneBy(['location' => $location, 'role' => RegisterRole::Main, 'active' => true]);
    }

    #[Override]
    public function findTerminal(string $code): ?Terminal
    {
        return $this->entityManager->find(Terminal::class, $code);
    }

    #[Override]
    public function addTerminal(Terminal $terminal): void
    {
        $this->entityManager->persist($terminal);
    }

    #[Override]
    public function terminalsAt(string $register): array
    {
        return $this->entityManager->getRepository(Terminal::class)->findBy(['register' => $register, 'active' => true], ['code' => 'ASC']);
    }

    #[Override]
    public function findPreset(string $code): ?SalePreset
    {
        return $this->entityManager->find(SalePreset::class, $code);
    }

    #[Override]
    public function addPreset(SalePreset $preset): void
    {
        $this->entityManager->persist($preset);
    }

    #[Override]
    public function activePresets(): array
    {
        return $this->entityManager->getRepository(SalePreset::class)->findBy(['active' => true], ['code' => 'ASC']);
    }

    #[Override]
    public function findCashierPin(string $user): ?CashierPin
    {
        return $this->entityManager->find(CashierPin::class, $user);
    }

    #[Override]
    public function addCashierPin(CashierPin $pin): void
    {
        $this->entityManager->persist($pin);
    }

    #[Override]
    public function removeCashierPin(CashierPin $pin): void
    {
        $this->entityManager->remove($pin);
    }

    #[Override]
    public function findSession(string $id): ?CashSession
    {
        return $this->entityManager->find(CashSession::class, $id);
    }

    #[Override]
    public function openSessionOf(string $register): ?CashSession
    {
        return $this->entityManager->getRepository(CashSession::class)->findOneBy(['register' => $register, 'state' => SessionState::Open]);
    }

    #[Override]
    public function addSession(CashSession $session): void
    {
        $this->entityManager->persist($session);
    }

    #[Override]
    public function addCashMovement(CashMovement $movement): void
    {
        $this->entityManager->persist($movement);
    }

    #[Override]
    public function cashMovementsOf(string $session): array
    {
        return $this->entityManager->getRepository(CashMovement::class)->findBy(['session' => $session], ['occurredAt' => 'ASC', 'id' => 'ASC']);
    }

    #[Override]
    public function cashMovementOf(string $payment): ?CashMovement
    {
        return $this->entityManager->getRepository(CashMovement::class)->findOneBy(['payment' => $payment]);
    }

    #[Override]
    public function closedBetween(DateTimeImmutable $from, DateTimeImmutable $to, ?string $register = null): array
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(CashSession::class, 's')
            ->where('s.state = :closed')
            ->andWhere('s.closedAt >= :from')
            ->andWhere('s.closedAt < :to')
            ->orderBy('s.closedAt', SortDirection::Ascending)
            ->addOrderBy('s.id', SortDirection::Ascending)
            ->setParameter('closed', SessionState::Closed)
            ->setParameter('from', $from, 'datetime_utc_immutable')
            ->setParameter('to', $to, 'datetime_utc_immutable');

        if (null !== $register) {
            $query->andWhere('s.register = :register')->setParameter('register', $register);
        }

        /** @var list<CashSession> */
        return $query->getQuery()->getResult();
    }

    #[Override]
    public function addSale(RegisterSale $sale): void
    {
        $this->entityManager->persist($sale);
    }

    #[Override]
    public function salesOf(string $session): array
    {
        return $this->entityManager->getRepository(RegisterSale::class)->findBy(['session' => $session], ['rungAt' => 'ASC']);
    }

    #[Override]
    public function addPayment(SessionPayment $payment): void
    {
        $this->entityManager->persist($payment);
    }

    #[Override]
    public function findPayment(string $payment): ?SessionPayment
    {
        return $this->entityManager->find(SessionPayment::class, $payment);
    }

    #[Override]
    public function paymentsOf(string $session): array
    {
        return $this->entityManager->getRepository(SessionPayment::class)->findBy(['session' => $session], ['takenAt' => 'ASC']);
    }

    #[Override]
    public function findHeld(string $orderNumber): ?HeldSale
    {
        return $this->entityManager->find(HeldSale::class, $orderNumber);
    }

    #[Override]
    public function hold(HeldSale $sale): void
    {
        $this->entityManager->persist($sale);
    }

    #[Override]
    public function release(HeldSale $sale): void
    {
        $this->entityManager->remove($sale);
    }

    #[Override]
    public function heldAt(string $register): array
    {
        return $this->entityManager->getRepository(HeldSale::class)->findBy(['register' => $register], ['heldAt' => 'ASC']);
    }

    #[Override]
    public function findLayaway(string $orderNumber): ?Layaway
    {
        return $this->entityManager->find(Layaway::class, $orderNumber);
    }

    #[Override]
    public function addLayaway(Layaway $layaway): void
    {
        $this->entityManager->persist($layaway);
    }

    #[Override]
    public function layawaysDue(DateTimeImmutable $at): array
    {
        /** @var list<Layaway> */
        return $this->entityManager->createQueryBuilder()
            ->select('l')
            ->from(Layaway::class, 'l')
            ->where('l.state = :open')
            ->andWhere('l.dueAt <= :at')
            ->orderBy('l.dueAt', SortDirection::Ascending)
            ->addOrderBy('l.orderNumber', SortDirection::Ascending)
            ->setParameter('open', LayawayState::Open)
            ->setParameter('at', $at, 'datetime_utc_immutable')
            ->getQuery()
            ->getResult();
    }

    #[Override]
    public function findImported(string $id): ?ImportedSale
    {
        return $this->entityManager->find(ImportedSale::class, $id);
    }

    #[Override]
    public function addImported(ImportedSale $sale): void
    {
        $this->entityManager->persist($sale);
    }

    #[Override]
    public function findTerminalPayment(string $payment): ?TerminalPayment
    {
        return $this->entityManager->find(TerminalPayment::class, $payment);
    }

    #[Override]
    public function addTerminalPayment(TerminalPayment $payment): void
    {
        $this->entityManager->persist($payment);
    }
}
