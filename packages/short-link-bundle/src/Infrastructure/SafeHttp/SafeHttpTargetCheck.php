<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Infrastructure\SafeHttp;

use Override;
use Phil\SafeHttp\Exception\FetchFailed;
use Phil\SafeHttp\Exception\RefusedAddress;
use Phil\SafeHttp\Exception\TooManyRedirects;
use Phil\SafeHttp\SafeHttpClient;
use Phil\ShortLinkBundle\Application\Contract\TargetCheck;
use Phil\Url\Url;

/**
 * Reads the target through phil/safe-http before a link to it is saved: an address on a private network, or one that
 * redirects there, is refused, and so is a target that does not answer in time, redirects too often or answers with
 * an error status. A server that refuses `HEAD` is asked with a `GET`, whose body is not read past its first bytes.
 */
final readonly class SafeHttpTargetCheck implements TargetCheck
{
    public function __construct(
        private SafeHttpClient $client,
    ) {
    }

    #[Override]
    public function refusal(Url $target): ?string
    {
        try {
            $response = $this->client->head($target);

            if (405 === $response->status || 501 === $response->status) {
                $response = $this->client->get($target);
            }
        } catch (RefusedAddress) {
            return 'the address is not public';
        } catch (TooManyRedirects) {
            return 'it redirects too many times';
        } catch (FetchFailed) {
            return 'it does not answer';
        }

        return $response->status >= 400 ? \sprintf('it answers with the status %d', $response->status) : null;
    }
}
