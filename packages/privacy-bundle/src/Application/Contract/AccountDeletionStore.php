<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Contract;

use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\AccountDeletion\AccountDeletion;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;

/**
 * Where account deletions are kept between the request and the end of the delay.
 */
interface AccountDeletionStore
{
    public function find(string $id): ?AccountDeletion;

    /**
     * The request of this person that is not over yet, if there is one.
     */
    public function openFor(DataSubject $subject): ?AccountDeletion;

    public function save(AccountDeletion $deletion): void;

    /**
     * The requests whose delay is over and that still have something to do, the oldest first.
     *
     * @return list<string> their identifiers
     */
    public function dueIdentifiers(DateTimeImmutable $now, int $limit): array;
}
