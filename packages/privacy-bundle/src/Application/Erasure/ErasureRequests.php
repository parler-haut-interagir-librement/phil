<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Erasure;

use Phil\PrivacyBundle\Application\Contract\ErasureMessages;
use Phil\PrivacyBundle\Application\Contract\ErasureRequestStore;
use Phil\PrivacyBundle\Application\Contract\ExternalPersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\GuestRequestStore;
use Phil\PrivacyBundle\Application\Contract\GuestSubjects;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Exception\MisconfiguredPrivacy;
use Phil\PrivacyBundle\Application\Exception\ModuleErasureFailed;
use Phil\PrivacyBundle\Domain\Erasure\AttentionReason;
use Phil\PrivacyBundle\Domain\Erasure\ErasureInventory;
use Phil\PrivacyBundle\Domain\Erasure\ErasureProgress;
use Phil\PrivacyBundle\Domain\Erasure\ErasureRequest;
use Phil\PrivacyBundle\Domain\Erasure\ErasureStep;
use Phil\PrivacyBundle\Domain\Erasure\RequestState;
use Phil\PrivacyBundle\Domain\Erasure\StepState;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\GuestRequest\GuestRequest;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use UnexpectedValueException;

/**
 * Erasure as a workflow: a request, one step per module, each run, retried and finished on its own.
 *
 * **What a request erases** is drawn from the erasure inventory, the modules whose erasers are declared, and kept on
 * the request with the inventory's version; a module that is not declared is refused.
 *
 * **One message per module.** Opening a request writes it, its pending steps and one outbox record per module in one
 * transaction: the outbox relays an `EraseModule` message for each, carrying the request's identifier and the module's
 * name only, with an idempotency key made of both. A worker leases the step, runs the module's eraser, and completes
 * the step with its report; a step already completed, or leased to another worker, is left alone, so that a message
 * delivered twice and a request resumed never run a finished module again.
 *
 * **Failures.** A module that fails gives its step back and throws `ModuleErasureFailed`, so that Messenger tries the
 * message again; once Messenger gives up, the step needs attention, and so does the request. A replay puts those steps
 * back and the request back to running. Only an operator closes a request as failed.
 *
 * **External services.** A module whose eraser is an `ExternalPersonalDataEraser` sends the erasure with the step's
 * idempotency key, and its step stays running until `acknowledge()` receives the service's answer under that key.
 *
 * **Requests made without an account** keep the identifier of the request they came from: the person is resolved in
 * the worker, through the store of those requests and `GuestSubjects`, and the request they came from is removed once
 * the erasure is closed.
 *
 * The service keeps no state between calls: one instance serves every message of a worker.
 */
final readonly class ErasureRequests
{
    /** How long a worker holds a step, in seconds, unless the application chooses otherwise: fifteen minutes. */
    public const int LEASE = 900;

    private ErasureInventory $inventory;

    /** @var array<string, PersonalDataEraser|ExternalPersonalDataEraser> */
    private array $erasers;

    private LoggerInterface $logger;

    /**
     * @param iterable<PersonalDataEraser>         $erasers
     * @param iterable<ExternalPersonalDataEraser> $externalErasers
     * @param int                                  $lease           how long a worker holds a step, in seconds, from 1 to
     *                                                              86,400: longer than the slowest eraser, or another
     *                                                              worker runs the module again
     *
     * @throws MisconfiguredPrivacy when two erasers declare one module, or the lease is out of bounds
     */
    public function __construct(
        private ErasureRequestStore $store,
        private ErasureMessages $messages,
        iterable $erasers,
        iterable $externalErasers,
        private ClockInterface $clock,
        private ?GuestRequestStore $guests = null,
        private ?GuestSubjects $guestSubjects = null,
        private int $lease = self::LEASE,
        ?LoggerInterface $logger = null,
    ) {
        if ($lease < 1 || $lease > 86_400) {
            throw MisconfiguredPrivacy::erasureLease($lease);
        }

        $byModule = [];

        foreach ([...$erasers, ...$externalErasers] as $eraser) {
            $module = $eraser->module()->value;

            if (isset($byModule[$module])) {
                throw MisconfiguredPrivacy::duplicateModule($module);
            }

            $byModule[$module] = $eraser;
        }

        $this->erasers = $byModule;
        $this->inventory = ErasureInventory::of(
            array_map(static fn (PersonalDataEraser|ExternalPersonalDataEraser $eraser): ModuleName => $eraser->module(), array_values(array_filter($byModule, static fn (PersonalDataEraser|ExternalPersonalDataEraser $eraser): bool => $eraser instanceof PersonalDataEraser))),
            array_map(static fn (PersonalDataEraser|ExternalPersonalDataEraser $eraser): ModuleName => $eraser->module(), array_values(array_filter($byModule, static fn (PersonalDataEraser|ExternalPersonalDataEraser $eraser): bool => $eraser instanceof ExternalPersonalDataEraser))),
        );
        $this->logger = $logger ?? new NullLogger();
    }

    public function inventory(): ErasureInventory
    {
        return $this->inventory;
    }

    /**
     * Opens a member's request, or answers with the one already kept under the same identifier.
     *
     * @param list<string>|null $modules the modules to erase, or null for every module of the inventory
     * @param string|null       $id      the identifier, such as the one of the account deletion it belongs to
     *
     * @throws InvalidPrivacyValue when a module is not in the inventory, or the identifier is not one
     */
    public function open(DataSubject $subject, ?array $modules = null, ?string $id = null): ErasureRequest
    {
        if (null !== $id) {
            $kept = $this->store->find($id);

            if ($kept instanceof ErasureRequest) {
                return $kept;
            }
        }

        $selected = $this->inventory->select($modules);
        $request = ErasureRequest::forSubject($subject, $this->inventory->version, $this->clock->now(), $id);
        $this->start($request, $selected);

        return $request;
    }

    /**
     * Opens the request of a person without an account, from the request whose link they opened.
     *
     * @param list<string>|null $modules the modules to erase, or null for every module of the inventory
     *
     * @return array{ErasureRequest, string} the request and the secret of its progress link, which is never kept
     *
     * @throws InvalidPrivacyValue when a module is not in the inventory
     */
    public function openForGuest(string $guestRequestId, ?array $modules = null): array
    {
        $selected = $this->inventory->select($modules);
        [$request, $token] = ErasureRequest::forGuest($guestRequestId, $this->inventory->version, $this->clock->now());
        $this->start($request, $selected);

        return [$request, $token];
    }

    /**
     * @phpstan-impure the request is read from its store each time
     */
    public function find(string $id): ?ErasureRequest
    {
        return 1 === preg_match(ErasureRequest::ID_PATTERN, $id) ? $this->store->find($id) : null;
    }

    /**
     * The request and its steps, or null when it is unknown.
     *
     * @phpstan-impure the request is read from its store each time
     */
    public function progress(string $id): ?ErasureProgress
    {
        $request = $this->find($id);

        return $request instanceof ErasureRequest ? new ErasureProgress($request, $this->store->steps($id)) : null;
    }

    /**
     * Runs one module's step of a request. Nothing happens to an unknown or closed request, to a module it does not
     * name, or to a step completed, waiting for its acknowledgement, or leased to another worker.
     *
     * @throws ModuleErasureFailed when the module fails: its step is given back, for Messenger to try again
     */
    public function run(string $id, string $module): void
    {
        $request = $this->find($id);

        if (!$request instanceof ErasureRequest || $request->state->isClosed()) {
            return;
        }

        if (!$this->store->step($id, $module) instanceof ErasureStep) {
            $this->logger->notice('Erasure request {id}: the module {module} is not one of its modules; the message is refused.', ['id' => $id, 'module' => $module]);

            return;
        }

        $eraser = $this->erasers[$module] ?? null;

        if (null === $eraser) {
            $this->flag($id, $module, AttentionReason::UnknownModule);

            return;
        }

        $lease = sodium_bin2hex(random_bytes(16));
        $now = $this->clock->now();

        if (!$this->store->lease($id, $module, $lease, $now, $now->modify(\sprintf('+%d seconds', $this->lease)))) {
            return;
        }

        if (RequestState::NeedsAttention === $request->state) {
            $this->refresh($id);
        }

        $subject = $this->subjectOf($request);

        if (!$subject instanceof DataSubject) {
            $this->flag($id, $module, AttentionReason::SubjectUnavailable);

            return;
        }

        try {
            if ($eraser instanceof ExternalPersonalDataEraser) {
                $eraser->request($subject, ErasureStep::keyOf($id, $module));
                $this->store->await($id, $module, $lease, $this->clock->now());
                $this->logger->info('Erasure request {id}: the module {module} was sent, and waits for its acknowledgement.', ['id' => $id, 'module' => $module]);

                return;
            }

            $report = $eraser->erase($subject);

            // A report filed under another module's name would mark that module done when it never ran.
            if ($report->module->value !== $module) {
                throw new UnexpectedValueException('The eraser returned a report for another module.');
            }
        } catch (Throwable $throwable) {
            $this->store->release($id, $module, $lease, $this->clock->now());
            $this->logger->warning('Erasure request {id}: the module {module} failed with {class}; the step is given back.', ['id' => $id, 'module' => $module, 'class' => $throwable::class]);

            throw ModuleErasureFailed::of($id, $module);
        }

        if ($this->store->complete($id, $module, $lease, $report->effects, $this->clock->now())) {
            $this->logger->info('Erasure request {id}: the module {module} completed.', ['id' => $id, 'module' => $module]);
        }

        $this->refresh($id);
    }

    /**
     * Completes the step an external service acknowledged, found by the idempotency key it received.
     *
     * @return bool false when no step waits for an acknowledgement under that key, or the report names another module
     */
    public function acknowledge(string $idempotencyKey, ErasureReport $report): bool
    {
        $parts = explode(':', $idempotencyKey, 2);

        if (2 !== \count($parts) || $parts[1] !== $report->module->value || !$this->find($parts[0]) instanceof ErasureRequest) {
            return false;
        }

        if (!$this->store->acknowledge($idempotencyKey, $report->effects, $this->clock->now())) {
            return false;
        }

        $this->logger->info('Erasure request {id}: the module {module} was acknowledged.', ['id' => $parts[0], 'module' => $parts[1]]);
        $this->refresh($parts[0]);

        return true;
    }

    /**
     * Marks a step as needing attention once Messenger gave up on its message, and the request with it.
     */
    public function giveUp(string $id, string $module): void
    {
        $this->flag($id, $module, AttentionReason::RetriesExhausted);
    }

    /**
     * Puts back the steps that need attention or wait for an acknowledgement, sends a message for every pending step,
     * and brings the request back to running.
     *
     * @return list<string> the modules sent again; none when the request is unknown, closed or has nothing left to run
     */
    public function replay(string $id): array
    {
        $request = $this->find($id);

        if (!$request instanceof ErasureRequest || $request->state->isClosed()) {
            return [];
        }

        $this->store->reopen($id, $this->clock->now());
        $pending = array_values(array_map(
            static fn (ErasureStep $step): string => $step->module,
            array_filter($this->store->steps($id), static fn (ErasureStep $step): bool => StepState::Pending === $step->state),
        ));

        foreach ($pending as $module) {
            // A key of its own: the first record of the step keeps its key until the outbox purges it.
            $this->messages->record($id, $module, ErasureStep::keyOf($id, $module).':'.sodium_bin2hex(random_bytes(4)));
        }

        $this->refresh($id);

        if ([] !== $pending) {
            $this->logger->info('Erasure request {id} replayed for {modules}.', ['id' => $id, 'modules' => $pending]);
        }

        return $pending;
    }

    /**
     * Closes a request as failed: an operator's decision, for a request that cannot be completed.
     *
     * @return bool false when the request is unknown or already closed
     */
    public function fail(string $id): bool
    {
        $request = $this->find($id);

        if (!$request instanceof ErasureRequest || !$this->store->settle($id, RequestState::Failed, $this->clock->now())) {
            return false;
        }

        $this->logger->warning('Erasure request {id} closed as failed by an operator.', ['id' => $id]);
        $this->closed($request);

        return true;
    }

    /**
     * @param list<string> $modules
     */
    private function start(ErasureRequest $request, array $modules): void
    {
        $steps = array_map(static fn (string $module): ErasureStep => ErasureStep::pending($request->id, $module, $request->requestedAt), $modules);

        $this->store->open($request, $steps, function () use ($request, $modules): void {
            foreach ($modules as $module) {
                $this->messages->record($request->id, $module, ErasureStep::keyOf($request->id, $module));
            }
        });

        $this->logger->info('Erasure request {id} opened for {modules}.', ['id' => $request->id, 'modules' => $modules, 'inventory' => $request->inventoryVersion]);
    }

    private function flag(string $id, string $module, AttentionReason $reason): void
    {
        if ($this->store->flag($id, $module, $reason, $this->clock->now())) {
            $this->logger->warning('Erasure request {id}: the module {module} needs attention ({reason}).', ['id' => $id, 'module' => $module, 'reason' => $reason->value]);
        }

        $this->refresh($id);
    }

    /**
     * Draws the state of the request from its steps.
     */
    private function refresh(string $id): void
    {
        $request = $this->store->find($id);

        if (!$request instanceof ErasureRequest || $request->state->isClosed()) {
            return;
        }

        $states = array_map(static fn (ErasureStep $step): StepState => $step->state, $this->store->steps($id));

        if ([] !== $states && [] === array_filter($states, static fn (StepState $state): bool => StepState::Completed !== $state)) {
            if ($this->store->settle($id, RequestState::Completed, $this->clock->now())) {
                $this->logger->info('Erasure request {id} completed.', ['id' => $id]);
                $this->closed($request);
            }

            return;
        }

        $this->store->settle($id, \in_array(StepState::NeedsAttention, $states, true) ? RequestState::NeedsAttention : RequestState::Running, null);
    }

    /**
     * Removes the request made without an account that a closed erasure came from, and its sealed address with it.
     */
    private function closed(ErasureRequest $request): void
    {
        if (null !== $request->guestRequestId) {
            $this->guests?->delete($request->guestRequestId);
        }
    }

    private function subjectOf(ErasureRequest $request): ?DataSubject
    {
        if ($request->subject instanceof DataSubject) {
            return $request->subject;
        }

        $guest = null === $request->guestRequestId ? null : $this->guests?->find($request->guestRequestId);

        return $guest instanceof GuestRequest && $this->guestSubjects instanceof GuestSubjects ? $this->guestSubjects->subjectOf($guest->email) : null;
    }
}
