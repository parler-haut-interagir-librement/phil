<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Xapi;

use InvalidArgumentException;
use Override;
use Phil\CoursesBundle\Application\Contract\LearningRecordStore;
use Phil\CoursesBundle\Domain\Exception\RecordStoreUnavailable;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalLearningRecordStore;
use Phil\SafeHttp\Exception\SafeHttpException;
use Phil\SafeHttp\ReadRule;
use Phil\SafeHttp\SafeHttpClient;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Psr\Log\LoggerInterface;
use SensitiveParameter;

/**
 * Sends the statements to a remote xAPI learning record store, through the guarded client of phil/safe-http: each
 * statement is `PUT` to `<endpoint>/statements?statementId=<id>` with the xAPI version header, as JSON, with HTTP Basic
 * authentication when credentials are given. A store that has the statement already answers `409 Conflict`, which
 * counts as kept: the statement is sent once more only when the first answer was lost. Any other error, or a store out
 * of reach within the limits of the client, throws `RecordStoreUnavailable`.
 *
 * The state documents of the assignable units stay local, in the store inside the bundle on DBAL, and are never sent to
 * the remote store: they are the runtime data of the launches, read back by the units through the bundle's own
 * endpoint, and the State resource of xAPI names an agent the `LearningRecordStore` port does not give.
 *
 * A statement the remote store keeps is out of the platform's reach: xAPI gives no way to erase one. `endpoint` names
 * the store, without credentials, so that an access request can say where the statements of a learner went.
 *
 * The log names the statement identifier, the status and the kind of failure, never the statement, the registration,
 * the learner, the credentials nor what the store answered.
 */
final readonly class HttpLearningRecordStore implements LearningRecordStore
{
    /** The versions of xAPI the store may speak. */
    public const array VERSIONS = ['1.0.3', '2.0.0'];

    public string $endpoint;

    private string $statements;

    /** @var array<string, string> */
    private array $headers;

    /**
     * @param string                  $endpoint    the xAPI endpoint of the store, an `https` address without a query,
     *                                             such as `https://lrs.example.org/xapi/`
     * @param DbalLearningRecordStore $localStates the store inside the bundle, which keeps the state documents
     * @param string                  $version     the value of the `X-Experience-API-Version` header
     *
     * @throws InvalidArgumentException when the endpoint, the credentials or the version are not ones the store takes
     */
    public function __construct(
        private SafeHttpClient $client,
        string $endpoint,
        private DbalLearningRecordStore $localStates,
        ?string $username = null,
        #[SensitiveParameter]
        ?string $password = null,
        string $version = '1.0.3',
        private ?LoggerInterface $logger = null,
    ) {
        try {
            $url = Url::fromString($endpoint);
        } catch (InvalidUrl $invalidUrl) {
            throw new InvalidArgumentException('The endpoint of the learning record store is not an address.', 0, $invalidUrl);
        }

        if ('https' !== $url->scheme || null !== $url->query || null !== $url->fragment) {
            throw new InvalidArgumentException('The endpoint of the learning record store is an https address without a query or a fragment.');
        }

        if (!\in_array($version, self::VERSIONS, true)) {
            throw new InvalidArgumentException(\sprintf('The xAPI version is one of %s.', implode(', ', self::VERSIONS)));
        }

        if ((null === $username) !== (null === $password) || str_contains((string) $username, ':')) {
            throw new InvalidArgumentException('The credentials of the learning record store are a user name without ":" and a password, or neither.');
        }

        $this->endpoint = rtrim($url->toString(), '/').'/';
        $this->statements = $this->endpoint.'statements';
        $this->headers = ['X-Experience-API-Version' => $version, 'Content-Type' => 'application/json']
            + (null === $username ? [] : ['Authorization' => 'Basic '.sodium_bin2base64($username.':'.$password, \SODIUM_BASE64_VARIANT_ORIGINAL)]);
    }

    /**
     * @throws RecordStoreUnavailable when the store does not keep the statement
     */
    #[Override]
    public function store(string $statementId, string $statement, string $registration): void
    {
        try {
            $response = $this->client->send('PUT', $this->statements.'?statementId='.rawurlencode($statementId), $this->headers, $statement, new ReadRule('application/json', ['application/json']));
        } catch (SafeHttpException $safeHttpException) {
            $this->logger?->warning('The learning record store could not be reached for the statement {statement}: {failure}.', ['statement' => $statementId, 'failure' => $safeHttpException::class]);

            throw RecordStoreUnavailable::unreachable($safeHttpException);
        }

        if (409 === $response->status) {
            $this->logger?->info('The learning record store has the statement {statement} already.', ['statement' => $statementId]);

            return;
        }

        if (!$response->isSuccessful()) {
            $this->logger?->warning('The learning record store refused the statement {statement}: HTTP {status}.', ['statement' => $statementId, 'status' => $response->status]);

            throw RecordStoreUnavailable::status($response->status);
        }
    }

    /**
     * Local: the store inside the bundle, never the remote store.
     */
    #[Override]
    public function state(string $activityId, string $registration, string $stateId): ?string
    {
        return $this->localStates->state($activityId, $registration, $stateId);
    }

    /**
     * Local: the store inside the bundle, never the remote store.
     */
    #[Override]
    public function saveState(string $activityId, string $registration, string $stateId, string $document): void
    {
        $this->localStates->saveState($activityId, $registration, $stateId, $document);
    }

    /**
     * Local: the store inside the bundle, never the remote store.
     */
    #[Override]
    public function deleteState(string $activityId, string $registration, string $stateId): void
    {
        $this->localStates->deleteState($activityId, $registration, $stateId);
    }
}
