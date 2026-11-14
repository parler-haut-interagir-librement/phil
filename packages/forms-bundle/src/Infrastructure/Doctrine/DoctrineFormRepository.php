<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\FormsBundle\Domain\Contract\ChoicePlaces;
use Phil\FormsBundle\Domain\Model\ChoiceCount;
use Phil\FormsBundle\Domain\Model\Entry;
use Phil\FormsBundle\Domain\Model\Form;
use Phil\FormsBundle\Domain\Repository\FormRepository;

/**
 * Forms, their entries and the places taken in their choices, with Doctrine. A place is counted in one statement,
 * `taken = taken + 1` under the capacity, so that the database settles two entries sent at once; an entry removed
 * gives its places back.
 */
final readonly class DoctrineFormRepository implements FormRepository, ChoicePlaces
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function save(Form|Entry ...$objects): void
    {
        foreach ($objects as $object) {
            $this->entityManager->persist($object);
        }

        $this->entityManager->flush();
    }

    /**
     * A form leaves with its entries and its counts, whether or not the database enforces its foreign keys; an entry
     * gives back the places it took.
     */
    #[Override]
    public function remove(Form|Entry ...$objects): void
    {
        $connection = $this->entityManager->getConnection();

        foreach ($objects as $object) {
            if ($object instanceof Form) {
                // By the table: the entries of a form are many, and none of them is loaded to be removed.
                $connection->delete($this->table(Entry::class), ['form' => $object->id]);
                $connection->delete($this->table(ChoiceCount::class), ['form' => $object->id]);
            } else {
                $this->giveBack($object);
            }

            $this->entityManager->remove($object);
        }

        $this->entityManager->flush();
    }

    #[Override]
    public function take(Form $form, string $field, string $choice, int $capacity): bool
    {
        if ($capacity < 1) {
            return false;
        }

        if ($this->increment($form, $field, $choice, $capacity)) {
            return true;
        }

        try {
            $this->entityManager->getConnection()->insert($this->table(ChoiceCount::class), ['form' => $form->id, 'field_name' => $field, 'choice' => $choice, 'taken' => 1]);

            return true;
        } catch (UniqueConstraintViolationException) {
            // Another entry counted the first place meanwhile: the row is there now.
            return $this->increment($form, $field, $choice, $capacity);
        }
    }

    #[Override]
    public function release(Form $form, string $field, string $choice): void
    {
        $this->entityManager->getConnection()->executeStatement(
            'UPDATE '.$this->table(ChoiceCount::class).' SET taken = taken - 1 WHERE form = ? AND field_name = ? AND choice = ? AND taken > 0',
            [$form->id, $field, $choice],
        );
    }

    #[Override]
    public function taken(Form $form, string $field, string $choice): int
    {
        $taken = $this->entityManager->getConnection()->fetchOne(
            'SELECT taken FROM '.$this->table(ChoiceCount::class).' WHERE form = ? AND field_name = ? AND choice = ?',
            [$form->id, $field, $choice],
        );

        return is_numeric($taken) ? (int) $taken : 0;
    }

    private function increment(Form $form, string $field, string $choice, int $capacity): bool
    {
        return 1 === $this->entityManager->getConnection()->executeStatement(
            'UPDATE '.$this->table(ChoiceCount::class).' SET taken = taken + 1 WHERE form = ? AND field_name = ? AND choice = ? AND taken < ?',
            [$form->id, $field, $choice, $capacity],
        );
    }

    private function giveBack(Entry $entry): void
    {
        foreach ($entry->form->fields() as $field) {
            $value = $entry->values[$field->name] ?? null;

            if (\is_string($value) && null !== $field->capacity($value)) {
                $this->release($entry->form, $field->name, $value);
            }
        }
    }

    /**
     * @param class-string $class
     */
    private function table(string $class): string
    {
        return $this->entityManager->getClassMetadata($class)->getTableName();
    }

    #[Override]
    public function get(string $id): ?Form
    {
        return $this->entityManager->find(Form::class, $id);
    }

    #[Override]
    public function byKey(string $key): ?Form
    {
        return $this->entityManager->getRepository(Form::class)->findOneBy(['key' => $key]);
    }

    #[Override]
    public function forms(): array
    {
        return $this->entityManager->getRepository(Form::class)->findBy([], ['key' => 'ASC']);
    }

    #[Override]
    public function entries(Form $form, int $limit = 50, int $offset = 0): array
    {
        return $this->entityManager->getRepository(Entry::class)->findBy(['form' => $form], ['submittedAt' => 'DESC', 'id' => 'DESC'], $limit, $offset);
    }

    #[Override]
    public function entry(string $id): ?Entry
    {
        return $this->entityManager->find(Entry::class, $id);
    }

    #[Override]
    public function expired(DateTimeImmutable $now, int $limit): array
    {
        /** @var list<Entry> */
        return $this->entityManager->createQueryBuilder()
            ->select('entry')
            ->from(Entry::class, 'entry')
            ->where('entry.purgeAfter <= :now')
            ->setParameter('now', $now->setTimezone(new DateTimeZone('UTC')), 'datetime_immutable')
            ->orderBy('entry.purgeAfter', 'ASC')
            ->addOrderBy('entry.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    #[Override]
    public function about(string $subject, string $emailDigest): array
    {
        /** @var list<Entry> */
        return $this->entityManager->createQueryBuilder()
            ->select('entry')
            ->from(Entry::class, 'entry')
            ->where('entry.subject = :subject')
            ->orWhere('entry.emailDigest = :digest')
            ->setParameter('subject', $subject)
            ->setParameter('digest', $emailDigest)
            ->orderBy('entry.submittedAt', 'ASC')
            ->addOrderBy('entry.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
