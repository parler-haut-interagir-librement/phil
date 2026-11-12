<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Infrastructure\Http;

use Phil\SafeHttp\Exception\SafeHttpException;
use Phil\SafeHttp\ReadRule;
use Phil\SafeHttp\SafeHttpClient;
use Phil\SocialCardsBundle\Application\Contract\PageFetcher;
use Phil\SocialCardsBundle\Application\FetchedPage;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Fetches the head of a page of another site through phil/safe-http, guarded against server-side request forgery:
 * an address of a host name only, a host that is or resolves to a private network refused at the first request and at
 * each redirect, the redirects, the silence, the whole duration and the bytes read bounded by the client's limits. Only
 * a `200` answer of HTML or XHTML is read, up to the end of its head, and anything that fails gives nothing.
 *
 * The client forgets the addresses it resolved between requests, so that a worker does not keep them for days.
 */
final readonly class GuardedPageFetcher implements PageFetcher, ResetInterface
{
    public function __construct(
        private SafeHttpClient $client = new SafeHttpClient(),
    ) {
    }

    public function fetch(string $url): ?FetchedPage
    {
        $rule = ReadRule::htmlHead();

        try {
            $response = $this->client->get($url, $rule);
        } catch (SafeHttpException) {
            return null;
        }

        if (200 !== $response->status || !$rule->reads($response->mediaType())) {
            return null;
        }

        return new FetchedPage($response->body, $response->url->toString(), $response->charset());
    }

    public function reset(): void
    {
        $this->client->reset();
    }
}
