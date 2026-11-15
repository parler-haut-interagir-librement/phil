<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommerceBundle\Domain\Contract\PaymentLinkRepository;
use Phil\CommerceBundle\Domain\Model\PaymentLink;

/**
 * `PaymentLinkRepository` on the Doctrine ORM: a link is one row of `phil_commerce_payment_link`, found by the hash
 * of its token or by its order.
 */
final readonly class DoctrinePaymentLinkRepository implements PaymentLinkRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function findPaymentLinkByTokenHash(string $tokenHash): ?PaymentLink
    {
        return $this->entityManager->getRepository(PaymentLink::class)->findOneBy(['tokenHash' => $tokenHash]);
    }

    #[Override]
    public function paymentLinksOf(string $orderNumber): array
    {
        return $this->entityManager->getRepository(PaymentLink::class)->findBy(['orderNumber' => $orderNumber], ['createdAt' => 'ASC', 'id' => 'ASC']);
    }

    #[Override]
    public function addPaymentLink(PaymentLink $link): void
    {
        $this->entityManager->persist($link);
    }
}
