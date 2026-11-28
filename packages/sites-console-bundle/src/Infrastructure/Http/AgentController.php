<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Http;

use Closure;
use Phil\Idempotency\Exception\IdempotencyKeyReused;
use Phil\Idempotency\Exception\InvalidIdempotencyValue;
use Phil\Idempotency\Exception\RequestInProgress;
use Phil\SitesConsoleBundle\Application\Agent\AgentEndpoints;
use Phil\SitesConsoleBundle\Application\Agent\RequestVerifier;
use Phil\SitesConsoleBundle\Application\Agent\VerifiedRequest;
use Phil\SitesConsoleBundle\Application\Exception\ActionUnavailable;
use Phil\SitesConsoleBundle\Application\Exception\ConfigurationRefused;
use Phil\SitesConsoleBundle\Application\Exception\KeyRefused;
use Phil\SitesConsoleBundle\Application\Exception\StateNotKept;
use Phil\SitesConsoleBundle\Domain\Document\Json;
use Phil\SitesConsoleBundle\Domain\Exception\MalformedDocument;
use Phil\SitesConsoleBundle\Domain\Exception\SignatureRefused;
use Phil\SitesConsoleBundle\Domain\Signature\Protocol;
use Phil\SitesConsoleBundle\Domain\Signature\SigningKey;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The agent's endpoints: `status`, `configuration`, `update` and `keys`. A request is refused unsigned — `401` — unless
 * the console signed it for this site, now, once; every answer after that is signed with the site's key over the
 * request's nonce, so the console knows it comes from the site.
 *
 * The routes are public: the body is read to one byte past its cap and no further before the signature is checked,
 * and a nonce the site could not keep is answered `503`, unsigned, rather than accepted.
 */
final readonly class AgentController
{
    /** The largest body the agent reads. */
    public const int MAX_BODY_BYTES = 65_536;

    public function __construct(
        private RequestVerifier $verifier,
        private AgentEndpoints $endpoints,
        private SigningKey $key,
        private string $siteId,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function status(Request $request): Response
    {
        return $this->handle(Protocol::ACTION_STATUS, $request, fn (): string => Json::encode($this->endpoints->status()->toArray()));
    }

    public function configuration(Request $request): Response
    {
        return $this->handle(Protocol::ACTION_CONFIGURATION, $request, fn (string $body): string => $this->endpoints->configure($body, self::MAX_BODY_BYTES)->toJson());
    }

    public function update(Request $request): Response
    {
        return $this->handle(Protocol::ACTION_UPDATE, $request, fn (string $body): string => $this->endpoints->update($body, self::MAX_BODY_BYTES)->toJson());
    }

    public function keys(Request $request): Response
    {
        return $this->handle(Protocol::ACTION_KEYS, $request, fn (string $body, VerifiedRequest $verified): string => $this->endpoints->keys($body, $verified->signer, self::MAX_BODY_BYTES)->toJson());
    }

    /**
     * @param Closure(string, VerifiedRequest): string $action
     */
    private function handle(string $name, Request $request, Closure $action): Response
    {
        $body = BoundedBody::read($request, self::MAX_BODY_BYTES);

        if (null === $body) {
            return $this->response(413, Json::encode(['error' => 'The request is too large.']));
        }

        try {
            $verified = $this->verifier->verify($name, $request->headers->get(Protocol::HEADER_SITE), $request->headers->get(Protocol::HEADER_DATE), $request->headers->get(Protocol::HEADER_NONCE), $request->headers->get(Protocol::HEADER_SIGNATURE), $body);
        } catch (SignatureRefused $signatureRefused) {
            $this->logger?->warning('A request to the agent was refused: {reason}', ['reason' => $signatureRefused->getMessage()]);

            return $this->response(401, Json::encode(['error' => 'The request is not signed by the console.']));
        } catch (StateNotKept $stateNotKept) {
            $this->logger?->error('A request to the agent was not accepted: {reason}', ['reason' => $stateNotKept->getMessage()]);

            $unavailable = $this->response(503, Json::encode(['error' => 'The site cannot accept the request now.']));
            $unavailable->headers->set('Retry-After', '60');

            return $unavailable;
        }

        $headers = [];

        try {
            [$status, $content] = [200, $action($body, $verified)];
        } catch (MalformedDocument|InvalidIdempotencyValue $malformed) {
            [$status, $content] = [400, Json::encode(['error' => $malformed->getMessage()])];
        } catch (ConfigurationRefused|KeyRefused|IdempotencyKeyReused $refused) {
            [$status, $content] = [422, Json::encode(['error' => $refused->getMessage()])];
        } catch (RequestInProgress $inProgress) {
            [$status, $content] = [409, Json::encode(['error' => 'The same request is still running.'])];
            $headers['Retry-After'] = (string) $inProgress->retryAfter;
        } catch (ActionUnavailable $unavailable) {
            [$status, $content] = [501, Json::encode(['error' => $unavailable->getMessage()])];
        }

        $response = $this->response($status, $content);
        $response->headers->add($headers);
        $response->headers->set(Protocol::HEADER_AGENT_SIGNATURE, $this->key->sign(Protocol::responseMessage($this->siteId, $verified->nonce, $status, $content)));

        return $response;
    }

    private function response(int $status, string $content): Response
    {
        return new Response($content, $status, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
