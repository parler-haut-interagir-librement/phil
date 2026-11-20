<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\GuestRequest;

use DateInterval;
use Phil\EmailAddress\EmailAddress;
use Phil\EmailAddress\Exception\InvalidEmailAddress;
use Phil\PrivacyBundle\Application\Contract\GuestLinkSender;
use Phil\PrivacyBundle\Application\Contract\GuestRequestStore;
use Phil\PrivacyBundle\Application\Contract\GuestSubjects;
use Phil\PrivacyBundle\Application\Erasure\ErasureRequests;
use Phil\PrivacyBundle\Application\Exception\ErasureIncomplete;
use Phil\PrivacyBundle\Application\Exception\LockLost;
use Phil\PrivacyBundle\Application\Exception\TooManyGuestRequests;
use Phil\PrivacyBundle\Application\Export\ExportPackage;
use Phil\PrivacyBundle\Application\Lock\HeldLock;
use Phil\PrivacyBundle\Application\SubjectErasure;
use Phil\PrivacyBundle\Application\SubjectExport;
use Phil\PrivacyBundle\Domain\Erasure\ErasureRequest;
use Phil\PrivacyBundle\Domain\GuestRequest\GuestRequest;
use Phil\PrivacyBundle\Domain\GuestRequest\GuestRequestKind;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SensitiveParameter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * The access and erasure requests of people without an account, proven by a link sent to their address.
 *
 * Guarded against abuse on every side:
 *
 * - **No enumeration.** A request is answered the same way whether the address is known or not: the link is sent,
 *   and what it finds is decided only once it is opened, by the mailbox's owner.
 * - **Two limits.** One per client, counted before anything is read, refuses a flood with the time to wait; one per
 *   address drops, silently, the links that would fill someone's mailbox. Both are keyed on a keyed hash: no address
 *   and no IP is written in the limiter's storage.
 * - **A link works once, for a short time.** Its secret has 256 random bits, only its hash is kept, and it is
 *   compared in constant time. Opening it shows a page; only the form of that page acts, so that a mail scanner that
 *   follows links does nothing.
 * - **One execution at a time.** An export or an erasure runs under a lock named after the request, which is read
 *   again once the lock is held and removed when it is done. The lock lives `LOCK_TTL` seconds and is renewed before
 *   each module and before the request is removed: an execution whose lock expired and was taken by another stops
 *   there, keeps the request, and answers as a link that no longer opens it.
 */
final readonly class GuestRequests
{
    public const float LOCK_TTL = 300.0;

    /** How long a request whose erasure runs as a workflow is kept at most, so that its modules can find the person. */
    public const string WORKFLOW_RETENTION = 'P90D';

    private string $key;

    private DateInterval $lifetime;

    private LoggerInterface $logger;

    public function __construct(
        private GuestRequestStore $store,
        private GuestLinkSender $sender,
        private GuestSubjects $subjects,
        private SubjectExport $export,
        private SubjectErasure $erasure,
        private ExportPackage $package,
        private LockFactory $locks,
        private ClockInterface $clock,
        private RateLimiterFactoryInterface $perClient,
        private RateLimiterFactoryInterface $perAddress,
        #[SensitiveParameter]
        string $secret,
        string $linkLifetime = 'PT1H',
        ?LoggerInterface $logger = null,
        private ?ErasureRequests $workflow = null,
    ) {
        $this->key = hash_hmac('sha256', 'phil_privacy.guest_requests', $secret, true);
        $this->lifetime = new DateInterval($linkLifetime);
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Sends a link to the address, unless it already received too many.
     *
     * @param string $client what tells the clients apart, such as the IP of the request
     *
     * @throws TooManyGuestRequests when the client is over its limit
     * @throws InvalidEmailAddress  when the address cannot receive mail
     */
    public function submit(GuestRequestKind $kind, string $address, string $client): void
    {
        $limit = $this->perClient->create($this->digest('client', $client))->consume();

        if (!$limit->isAccepted()) {
            // The limiter counts on the system's time, not on the clock of the application.
            throw TooManyGuestRequests::retryIn($limit->getRetryAfter()->getTimestamp() - time());
        }

        $email = EmailAddress::parse($address)->toString();
        $now = $this->clock->now();
        $this->store->purgeExpired($now);

        if (!$this->perAddress->create($this->digest('address', $email))->consume()->isAccepted()) {
            $this->logger->notice('A request without an account was dropped: its address received too many links.');

            return;
        }

        [$request, $token] = GuestRequest::issue($kind, $email, $now, $now->add($this->lifetime));
        $this->store->save($request);
        $this->sender->send($request, $token);
        $this->logger->info('Request without an account {id} sent.', ['id' => $request->id, 'kind' => $kind->value]);
    }

    /**
     * The request a link opens, or null when it is unknown, expired or opened by another secret.
     *
     * @phpstan-impure the request is read from its store each time, and removed once expired
     */
    public function open(string $id, string $token): ?GuestRequest
    {
        if (1 !== preg_match(GuestRequest::ID_PATTERN, $id)) {
            return null;
        }

        $request = $this->store->find($id);

        if (!$request instanceof GuestRequest) {
            return null;
        }

        if ($request->isExpired($this->clock->now())) {
            $this->store->delete($id);

            return null;
        }

        return $request->isOpenedBy($token) ? $request : null;
    }

    /**
     * The archive of what the site keeps about the person, or null when the link no longer opens an access request.
     */
    public function access(string $id, string $token): ?string
    {
        return $this->locked($id, $token, GuestRequestKind::Access, function (GuestRequest $request, HeldLock $lock): string {
            $zip = $this->package->zip($this->export->export($this->subjects->subjectOf($request->email), $lock));
            $lock->renew();
            $this->store->delete($request->id);
            $this->logger->info('Request without an account {id} answered.', ['id' => $request->id]);

            return $zip;
        });
    }

    /**
     * What each module did, or null when the link no longer opens an erasure request.
     *
     * @return list<ErasureReport>|null
     *
     * @throws ErasureIncomplete a module could not erase: the request stays, and the same link runs it again
     */
    public function erase(string $id, string $token): ?array
    {
        return $this->locked($id, $token, GuestRequestKind::Erasure, function (GuestRequest $request, HeldLock $lock): array {
            $reports = $this->erasure->erase($this->subjects->subjectOf($request->email), $lock);
            $lock->renew();
            $this->store->delete($request->id);
            $this->logger->info('Request without an account {id} answered.', ['id' => $request->id]);

            return $reports;
        });
    }

    /**
     * Whether an erasure opens a workflow request instead of running every module at once.
     */
    public function erasesAsAWorkflow(): bool
    {
        return $this->workflow instanceof ErasureRequests;
    }

    /**
     * Opens the erasure workflow of the request, or null when the link no longer opens an erasure request or no workflow
     * is configured. The request is kept, its link no longer opening it, until the erasure is closed or
     * `WORKFLOW_RETENTION` is over: the workers resolve the person from it, and no message carries them.
     *
     * @return array{ErasureRequest, string}|null the erasure request and the secret of its progress link
     */
    public function startErasure(string $id, string $token): ?array
    {
        $workflow = $this->workflow;

        if (!$workflow instanceof ErasureRequests) {
            return null;
        }

        return $this->locked($id, $token, GuestRequestKind::Erasure, function (GuestRequest $request, HeldLock $lock) use ($workflow): array {
            $started = $workflow->openForGuest($request->id);
            $lock->renew();
            $this->store->retain($request->id, $this->clock->now()->add(new DateInterval(self::WORKFLOW_RETENTION)));
            $this->logger->info('Request without an account {id} handed to the erasure request {erasure}.', ['id' => $request->id, 'erasure' => $started[0]->id]);

            return $started;
        });
    }

    /**
     * @template T
     *
     * @param callable(GuestRequest, HeldLock): T $work
     *
     * @return T|null
     */
    private function locked(string $id, string $token, GuestRequestKind $kind, callable $work): mixed
    {
        if (!$this->open($id, $token) instanceof GuestRequest) {
            return null;
        }

        $lock = $this->locks->createLock('phil_privacy.guest.'.$id, self::LOCK_TTL, false);

        if (!$lock->acquire()) {
            return null;
        }

        $held = new HeldLock($lock);

        try {
            // Read again under the lock: a request answered by another process is gone.
            $request = $this->open($id, $token);

            return $request instanceof GuestRequest && $request->kind === $kind ? $work($request, $held) : null;
        } catch (LockLost) {
            $this->logger->warning('Request without an account {id}: its lock was lost; the request is kept.', ['id' => $id]);

            return null;
        } finally {
            $held->release();
        }
    }

    private function digest(string $purpose, string $value): string
    {
        return hash_hmac('sha256', $purpose.'|'.$value, $this->key);
    }
}
