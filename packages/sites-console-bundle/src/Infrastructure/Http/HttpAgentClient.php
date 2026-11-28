<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Http;

use Override;
use Phil\SitesConsoleBundle\Application\Console\ConsoleSigning;
use Phil\SitesConsoleBundle\Application\Contract\AgentClient;
use Phil\SitesConsoleBundle\Application\Exception\AgentFailure;
use Phil\SitesConsoleBundle\Domain\Document\Fields;
use Phil\SitesConsoleBundle\Domain\Document\Json;
use Phil\SitesConsoleBundle\Domain\Exception\MalformedDocument;
use Phil\SitesConsoleBundle\Domain\Model\Site;
use Phil\SitesConsoleBundle\Domain\Signature\Protocol;
use Phil\SitesConsoleBundle\Domain\ValueObject\ConfigurationRequest;
use Phil\SitesConsoleBundle\Domain\ValueObject\KeyRequest;
use Phil\SitesConsoleBundle\Domain\ValueObject\Outcome;
use Phil\SitesConsoleBundle\Domain\ValueObject\StatusDocument;
use Phil\SitesConsoleBundle\Domain\ValueObject\UpdateRequest;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The agents reached over HTTP with the HttpClient component: each request signed with the key the console signs
 * with at that moment, no redirect followed, a time limit, a size limit on the answer, and the answer accepted only
 * when the site's key verifies its signature over the request's nonce.
 */
final readonly class HttpAgentClient implements AgentClient
{
    public function __construct(
        private HttpClientInterface $http,
        private ConsoleSigning $signing,
        private ClockInterface $clock,
        private float $timeout = 10.0,
        private float $updateTimeout = 600.0,
        private int $maxBytes = Json::MAX_BYTES,
    ) {
    }

    #[Override]
    public function status(Site $site): StatusDocument
    {
        $body = $this->send($site, Protocol::ACTION_STATUS, 'GET', '', $this->timeout);

        try {
            return StatusDocument::fromJson($body);
        } catch (MalformedDocument $malformedDocument) {
            throw AgentFailure::malformed($malformedDocument->getMessage(), $malformedDocument);
        }
    }

    #[Override]
    public function configure(Site $site, ConfigurationRequest $request): Outcome
    {
        return $this->outcome($this->send($site, Protocol::ACTION_CONFIGURATION, 'POST', $request->toJson(), $this->timeout));
    }

    #[Override]
    public function update(Site $site, UpdateRequest $request): Outcome
    {
        return $this->outcome($this->send($site, Protocol::ACTION_UPDATE, 'POST', $request->toJson(), $this->updateTimeout));
    }

    #[Override]
    public function keys(Site $site, KeyRequest $request): Outcome
    {
        return $this->outcome($this->send($site, Protocol::ACTION_KEYS, 'POST', $request->toJson(), $this->timeout));
    }

    private function outcome(string $body): Outcome
    {
        try {
            return Outcome::fromJson($body);
        } catch (MalformedDocument $malformedDocument) {
            throw AgentFailure::malformed($malformedDocument->getMessage(), $malformedDocument);
        }
    }

    /**
     * @return string the body of a verified `200` answer
     */
    private function send(Site $site, string $action, string $method, string $body, float $timeout): string
    {
        $nonce = Protocol::nonce();
        $date = $this->clock->now()->getTimestamp();
        $maxBytes = $this->maxBytes;
        $key = $this->signing->key();

        try {
            $response = $this->http->request($method, $site->agentUrl()->endpoint($action), [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    Protocol::HEADER_SITE => $site->id(),
                    Protocol::HEADER_DATE => (string) $date,
                    Protocol::HEADER_NONCE => $nonce,
                    Protocol::HEADER_SIGNATURE => $key->sign(Protocol::requestMessage($site->id(), $action, $date, $nonce, $body)),
                ],
                'body' => $body,
                'timeout' => $timeout,
                'max_duration' => $timeout,
                'max_redirects' => 0,
                'on_progress' => static function (int $downloaded) use ($maxBytes): void {
                    if ($downloaded > $maxBytes) {
                        throw AgentFailure::malformed(\sprintf('the answer is larger than %d bytes.', $maxBytes));
                    }
                },
            ]);
            $status = $response->getStatusCode();
            $content = $response->getContent(false);
            $signature = $response->getHeaders(false)[strtolower(Protocol::HEADER_AGENT_SIGNATURE)][0] ?? null;
        } catch (TransportExceptionInterface $transportException) {
            if ($transportException->getPrevious() instanceof AgentFailure) {
                throw $transportException->getPrevious();
            }

            throw AgentFailure::unreachable($transportException->getMessage(), $transportException);
        }

        if (\strlen($content) > $maxBytes) {
            throw AgentFailure::malformed(\sprintf('the answer is larger than %d bytes.', $maxBytes));
        }

        if (null === $signature || !$site->verifyingKey()->verifies(Protocol::responseMessage($site->id(), $nonce, $status, $content), $signature)) {
            throw AgentFailure::unsigned($status);
        }

        if (200 !== $status) {
            throw AgentFailure::status($status, $this->error($content));
        }

        return $content;
    }

    private function error(string $content): string
    {
        try {
            return Fields::optionalString(Json::decodeObject($content), 'error', Outcome::MESSAGE_MAX_LENGTH);
        } catch (MalformedDocument) {
            return '';
        }
    }
}
