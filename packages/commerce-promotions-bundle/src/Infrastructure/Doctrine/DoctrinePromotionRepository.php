<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Override;
use Phil\CommercePromotionsBundle\Domain\Contract\PromotionRepository;
use Phil\CommercePromotionsBundle\Domain\Model\Promotion;
use Phil\CommercePromotionsBundle\Domain\Model\PromotionCode;
use Phil\CommercePromotionsBundle\Domain\Model\PromotionCustomerUse;
use Phil\CommercePromotionsBundle\Domain\Model\PromotionRedemption;

/**
 * `PromotionRepository` on the Doctrine ORM: a promotion is one row of `phil_commerce_promotion`, a code one row of
 * `phil_commerce_promotion_code`, a redemption one row of `phil_commerce_promotion_redemption`, the uses of one
 * customer one row of `phil_commerce_promotion_customer_use`.
 *
 * A use is claimed by an SQL `UPDATE` that adds one only while the row is under its limit: the database serializes two
 * claims of the same row, and the second one sees the first one's count. A customer's first use inserts their row;
 * two first uses at once collide on its primary key, and the second placement fails and is rolled back. Claims are
 * made inside the transaction that places the order, so that they are committed or rolled back with it; outside one,
 * they are refused.
 */
final readonly class DoctrinePromotionRepository implements PromotionRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function find(string $code): ?Promotion
    {
        return $this->entityManager->find(Promotion::class, $code);
    }

    #[Override]
    public function active(): array
    {
        return $this->entityManager->getRepository(Promotion::class)->findBy(['active' => true], ['code' => 'ASC']);
    }

    #[Override]
    public function add(Promotion $promotion): void
    {
        $this->entityManager->persist($promotion);
    }

    #[Override]
    public function findCode(string $code): ?PromotionCode
    {
        return $this->entityManager->find(PromotionCode::class, $code);
    }

    #[Override]
    public function addCode(PromotionCode $code): void
    {
        $this->entityManager->persist($code);
    }

    #[Override]
    public function claimUse(string $promotion, ?string $code): bool
    {
        $this->checkTransaction();

        if (!$this->claim(Promotion::class, $promotion)) {
            return false;
        }

        if (null !== $code && !$this->claim(PromotionCode::class, $code)) {
            [$table, $id, $uses] = $this->columns(Promotion::class);
            $this->entityManager->getConnection()->executeStatement(\sprintf('UPDATE %1$s SET %3$s = %3$s - 1 WHERE %2$s = ?', $table, $id, $uses), [$promotion]);

            return false;
        }

        return true;
    }

    #[Override]
    public function customerUses(string $promotion, string $customer): int
    {
        return $this->entityManager->find(PromotionCustomerUse::class, ['promotion' => $promotion, 'customer' => $customer])?->uses() ?? 0;
    }

    #[Override]
    public function claimCustomerUse(string $promotion, string $customer, int $limit): bool
    {
        $this->checkTransaction();
        $metadata = $this->entityManager->getClassMetadata(PromotionCustomerUse::class);
        $table = $metadata->getTableName();
        $promotionColumn = $metadata->getColumnName('promotion');
        $customerColumn = $metadata->getColumnName('customer');
        $uses = $metadata->getColumnName('uses');
        $connection = $this->entityManager->getConnection();

        if (1 === $connection->executeStatement(\sprintf('UPDATE %1$s SET %4$s = %4$s + 1 WHERE %2$s = ? AND %3$s = ? AND %4$s < ?', $table, $promotionColumn, $customerColumn, $uses), [$promotion, $customer, $limit])) {
            return true;
        }

        if (false !== $connection->fetchOne(\sprintf('SELECT 1 FROM %1$s WHERE %2$s = ? AND %3$s = ?', $table, $promotionColumn, $customerColumn), [$promotion, $customer])) {
            return false;
        }

        $connection->insert($table, [$promotionColumn => $promotion, $customerColumn => $customer, $uses => 1]);

        return true;
    }

    #[Override]
    public function findRedemption(string $orderNumber, string $promotion): ?PromotionRedemption
    {
        return $this->entityManager->find(PromotionRedemption::class, ['orderNumber' => $orderNumber, 'promotion' => $promotion]);
    }

    #[Override]
    public function addRedemption(PromotionRedemption $redemption): void
    {
        $this->entityManager->persist($redemption);
    }

    private function checkTransaction(): void
    {
        if (!$this->entityManager->getConnection()->isTransactionActive()) {
            throw new LogicException('Uses of promotions are claimed inside the transaction that places the order: wrap it in EntityManagerInterface::wrapInTransaction().');
        }
    }

    /**
     * @param class-string<Promotion|PromotionCode> $class
     */
    private function claim(string $class, string $code): bool
    {
        [$table, $id, $uses, $limit] = $this->columns($class);

        return 1 === $this->entityManager->getConnection()->executeStatement(
            \sprintf('UPDATE %1$s SET %3$s = %3$s + 1 WHERE %2$s = ? AND (%4$s IS NULL OR %3$s < %4$s)', $table, $id, $uses, $limit),
            [$code],
        );
    }

    /**
     * The table and the columns of a counted row, as the mapping names them.
     *
     * @param class-string<Promotion|PromotionCode> $class
     *
     * @return array{string, string, string, string} table, key, uses, usage limit
     */
    private function columns(string $class): array
    {
        $metadata = $this->entityManager->getClassMetadata($class);

        return [$metadata->getTableName(), $metadata->getColumnName('code'), $metadata->getColumnName('uses'), $metadata->getColumnName('usageLimit')];
    }
}
