<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\AccountDeletion;

use DateInterval;
use DateTimeImmutable;
use Phil\PrivacyBundle\Application\Contract\AccountDeletionStore;
use Phil\PrivacyBundle\Application\Contract\ExportArchive;
use Phil\PrivacyBundle\Application\Erasure\ErasureRequests;
use Phil\PrivacyBundle\Application\Exception\AccountDeletionBusy;
use Phil\PrivacyBundle\Application\Exception\ErasureIncomplete;
use Phil\PrivacyBundle\Application\Exception\LockLost;
use Phil\PrivacyBundle\Application\Export\ExportPackage;
use Phil\PrivacyBundle\Application\Lock\HeldLock;
use Phil\PrivacyBundle\Application\SubjectErasure;
use Phil\PrivacyBundle\Application\SubjectExport;
use Phil\PrivacyBundle\Domain\AccountDeletion\AccountDeletion;
use Phil\PrivacyBundle\Domain\AccountDeletion\DeletionPlace;
use Phil\PrivacyBundle\Domain\Erasure\RequestState;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Deletes an account in stages: a request, a delay during which the person can change their mind, an export, then the
 * erasure of every module.
 *
 * **One execution per person.** Every stage runs under a lock named after a hash of the person's identifier, and the
 * request is read again once the lock is held: a cancellation that won the race is seen, and two workers cannot run
 * the same person twice.
 *
 * **A stage that fails is resumed, not restarted.** The place is saved after each stage: an export is written once,
 * and an incomplete erasure stays at `exported`, to be run again; erasers are idempotent. Log lines carry the identifier
 * of the request and the names of the modules, never the person or the text of an exception.
 *
 * **The erasure as a workflow.** With `ErasureRequests`, the erasure stage opens an erasure request under the
 * identifier of the deletion, once, and each module runs, is retried and completes on its own; the request stays at
 * `exported` until every module completed, and the next sweep closes it then. A request an operator closed as failed
 * stays at `exported` too, for the operator to decide.
 *
 * **A stage stops when its lock is lost.** The lock lives `LOCK_TTL` seconds and is renewed before each module of the
 * export and of the erasure, and before each place is saved: a stage whose lock expired and was taken by another
 * worker stops there, logs a warning, and leaves the request at its last saved place, for the worker that holds the
 * lock now.
 */
final readonly class AccountDeletions
{
    public const float LOCK_TTL = 900.0;

    private DateInterval $delay;

    private DateInterval $exportRetention;

    private LoggerInterface $logger;

    public function __construct(
        private AccountDeletionStore $store,
        private WorkflowInterface $workflow,
        private LockFactory $locks,
        private SubjectExport $export,
        private SubjectErasure $erasure,
        private ExportArchive $archive,
        private ClockInterface $clock,
        string $delay,
        string $exportRetention,
        ?LoggerInterface $logger = null,
        private ?ExportPackage $package = null,
        private ?ErasureRequests $erasureRequests = null,
    ) {
        $this->delay = new DateInterval($delay);
        $this->exportRetention = new DateInterval($exportRetention);
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Opens a request, or answers with the one already open.
     *
     * @throws AccountDeletionBusy
     */
    public function request(DataSubject $subject): AccountDeletion
    {
        $lock = $this->lockOf($subject);

        if (!$lock->acquire()) {
            throw AccountDeletionBusy::create();
        }

        try {
            $open = $this->store->openFor($subject);

            if ($open instanceof AccountDeletion) {
                return $open;
            }

            $now = $this->clock->now();
            $deletion = new AccountDeletion(sodium_bin2hex(random_bytes(16)), $subject, DeletionPlace::Requested, $now, $now->add($this->delay));
            $this->store->save($deletion);
            $this->logger->info('Account deletion {id} requested.', ['id' => $deletion->id]);

            return $deletion;
        } finally {
            $lock->release();
        }
    }

    /**
     * @return bool whether a request was cancelled; false when none is open or its delay is over
     *
     * @throws AccountDeletionBusy
     */
    public function cancel(DataSubject $subject): bool
    {
        $lock = $this->lockOf($subject);

        if (!$lock->acquire()) {
            throw AccountDeletionBusy::create();
        }

        try {
            $now = $this->clock->now();
            $open = $this->store->openFor($subject);

            if (!$open instanceof AccountDeletion || !$open->isCancellable($now)) {
                return false;
            }

            $open->closeAt($now);
            $this->workflow->apply($open, DeletionWorkflow::CANCEL);
            $this->store->save($open);
            $this->logger->info('Account deletion {id} cancelled.', ['id' => $open->id]);

            return true;
        } finally {
            $lock->release();
        }
    }

    public function openFor(DataSubject $subject): ?AccountDeletion
    {
        return $this->store->openFor($subject);
    }

    /**
     * @return list<string>
     */
    public function dueIdentifiers(int $limit): array
    {
        return $this->store->dueIdentifiers($this->clock->now(), $limit);
    }

    /**
     * Runs what is left of a request whose delay is over. Nothing happens to an unknown request, one that is not due,
     * or one another worker holds: the next sweep comes back to it.
     *
     * @return DeletionPlace|null where the request stands, or null when it is unknown
     *
     * @throws ErasureIncomplete a module could not erase: the request stays at `exported`, to be run again
     */
    public function advance(string $id): ?DeletionPlace
    {
        $found = $this->store->find($id);

        if (!$found instanceof AccountDeletion) {
            return null;
        }

        $lock = $this->lockOf($found->subject);

        if (!$lock->acquire()) {
            return $found->getPlace();
        }

        $held = new HeldLock($lock);

        try {
            return $this->advanceLocked($id, $held);
        } catch (LockLost) {
            $this->logger->warning('Account deletion {id}: its lock was lost; the stage stopped.', ['id' => $id]);

            return $this->store->find($id)?->getPlace();
        } finally {
            $held->release();
        }
    }

    public function purgeExpiredExports(): int
    {
        return $this->archive->purgeExpired($this->clock->now());
    }

    private function advanceLocked(string $id, HeldLock $lock): ?DeletionPlace
    {
        $deletion = $this->store->find($id);
        $now = $this->clock->now();

        if (!$deletion instanceof AccountDeletion || !$deletion->isDue($now)) {
            return $deletion?->getPlace();
        }

        if (DeletionPlace::Requested === $deletion->getPlace()) {
            $this->exportFor($deletion, $now, $lock);
        }

        $this->eraseFor($deletion, $now, $lock);

        return $deletion->getPlace();
    }

    private function exportFor(AccountDeletion $deletion, DateTimeImmutable $now, HeldLock $lock): void
    {
        $sections = $this->export->export($deletion->subject, $lock);
        $content = $this->package instanceof ExportPackage
            ? $this->package->zip($sections)
            : json_encode($sections, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $lock->renew();
        $this->archive->put($deletion->id, $content, $now->add($this->exportRetention));
        $deletion->exportedTo($deletion->id);
        $this->workflow->apply($deletion, DeletionWorkflow::EXPORT);
        $lock->renew();
        $this->store->save($deletion);
        $this->logger->info('Account deletion {id} exported.', ['id' => $deletion->id]);
    }

    private function eraseFor(AccountDeletion $deletion, DateTimeImmutable $now, HeldLock $lock): void
    {
        if ($this->erasureRequests instanceof ErasureRequests) {
            $erasure = $this->erasureRequests->open($deletion->subject, null, $deletion->id);

            if (RequestState::Completed !== $erasure->state) {
                $this->logger->info('Account deletion {id}: its erasure request is {state}.', ['id' => $deletion->id, 'state' => $erasure->state->value]);

                return;
            }

            $this->closeErased($deletion, $now, $lock);

            return;
        }

        try {
            $this->erasure->erase($deletion->subject, $lock);
        } catch (ErasureIncomplete $erasureIncomplete) {
            $this->logger->warning('Account deletion {id}: the erasure failed in {modules}.', ['id' => $deletion->id, 'modules' => array_keys($erasureIncomplete->failures)]);

            throw $erasureIncomplete;
        }

        $this->closeErased($deletion, $now, $lock);
    }

    private function closeErased(AccountDeletion $deletion, DateTimeImmutable $now, HeldLock $lock): void
    {
        $deletion->closeAt($now);
        $this->workflow->apply($deletion, DeletionWorkflow::ERASE);
        $lock->renew();
        $this->store->save($deletion);
        $this->logger->info('Account deletion {id} erased.', ['id' => $deletion->id]);
    }

    private function lockOf(DataSubject $subject): LockInterface
    {
        // Released by the `finally` of each stage only: a lock that a destructor would release hides a stage that forgets to.
        return $this->locks->createLock('phil_privacy.account_deletion.'.hash('xxh128', $subject->value), self::LOCK_TTL, false);
    }
}
