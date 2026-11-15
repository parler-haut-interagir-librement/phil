<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommerceBundle\Domain\Model\Receipt;
use Phil\CommerceBundle\Domain\Receipt\DocumentKind;
use Phil\CommerceBundle\Domain\Repository\ReceiptRepository;

/**
 * `ReceiptRepository` on the Doctrine ORM: a receipt or a credit note is one row of `phil_commerce_receipt`.
 */
final readonly class DoctrineReceiptRepository implements ReceiptRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function find(string $number): ?Receipt
    {
        return $this->entityManager->find(Receipt::class, $number);
    }

    #[Override]
    public function receiptOf(string $orderNumber): ?Receipt
    {
        return $this->entityManager->getRepository(Receipt::class)->findOneBy(['orderNumber' => $orderNumber, 'kind' => DocumentKind::Receipt]);
    }

    #[Override]
    public function creditNotesOf(string $receiptNumber): array
    {
        return $this->entityManager->getRepository(Receipt::class)->findBy(['creditedReceipt' => $receiptNumber], ['issuedAt' => 'ASC', 'number' => 'ASC']);
    }

    #[Override]
    public function add(Receipt $receipt): void
    {
        $this->entityManager->persist($receipt);
    }
}
