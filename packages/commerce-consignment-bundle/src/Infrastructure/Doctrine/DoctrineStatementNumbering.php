<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommerceConsignmentBundle\Application\Contract\StatementNumbering;
use Phil\CommerceConsignmentBundle\Domain\Exception\InvalidValue;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignorStatement;

/**
 * The statements' series in their own table: the prefix, then eight digits, one more than the highest number written
 * with that prefix — `ST-00000001`, `ST-00000002`. Two requests that take the same number at once cannot both flush:
 * the unique constraint on the number refuses the second, which issues its statement again.
 */
final readonly class DoctrineStatementNumbering implements StatementNumbering
{
    private const int DIGITS = 8;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private string $prefix = 'ST-',
    ) {
        if (1 !== preg_match('/^[A-Za-z0-9.#\/-]{0,24}$/D', $prefix)) {
            throw InvalidValue::because(\sprintf('A statement prefix has up to 24 letters, digits, dots, dashes, slashes or hashes; "%s" does not.', mb_substr($prefix, 0, 40)));
        }
    }

    #[Override]
    public function next(): string
    {
        $numbers = $this->entityManager->createQueryBuilder()
            ->select('statement.number')
            ->from(ConsignorStatement::class, 'statement')
            ->where('statement.number LIKE :prefix')
            ->setParameter('prefix', $this->prefix.str_repeat('_', self::DIGITS))
            ->orderBy('statement.number', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleColumnResult();

        foreach ($this->entityManager->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof ConsignorStatement) {
                $numbers[] = $entity->number;
            }
        }

        $last = 0;

        foreach ($numbers as $number) {
            if (\is_string($number) && 1 === preg_match('/^\d{8}$/D', substr($number, \strlen($this->prefix))) && str_starts_with($number, $this->prefix)) {
                $last = max($last, (int) substr($number, \strlen($this->prefix)));
            }
        }

        return $this->prefix.str_pad((string) ($last + 1), self::DIGITS, '0', \STR_PAD_LEFT);
    }
}
