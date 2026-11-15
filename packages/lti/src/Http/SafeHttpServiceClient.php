<?php

declare(strict_types=1);

namespace Phil\Lti\Http;

use Override;
use Phil\Lti\Contract\ServiceClient;
use Phil\Lti\Exception\ServiceFailed;
use Phil\SafeHttp\Exception\SafeHttpException;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\ReadRule;
use Phil\SafeHttp\SafeHttpClient;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Sends the requests of the services — the token endpoint, Assignment and Grade Services, Names and Role Provisioning
 * Services — through `SafeHttpClient::send()` of `phil/safe-http`: HTTPS only, every host checked against private and
 * reserved networks, no proxy of the environment, the headers kept on the origin of the address, the silence of the
 * host, the whole duration and the bytes of the answer bounded. By default no redirect is followed and an answer is
 * read up to 4 MiB; a client of other limits is given to the constructor.
 */
final readonly class SafeHttpServiceClient implements ServiceClient, ResetInterface
{
    public const int DEFAULT_MAX_BYTES = 4_194_304;

    public const string USER_AGENT = 'PhilLti/0.1';

    private SafeHttpClient $client;

    public function __construct(?SafeHttpClient $client = null)
    {
        $this->client = $client ?? new SafeHttpClient(limits: self::defaultLimits());
    }

    /**
     * The limits of the default client: 5 seconds of silence, 20 seconds in all, 4 MiB of answer, no redirect.
     */
    public static function defaultLimits(): Limits
    {
        return new Limits(timeout: 5.0, maxDuration: 20.0, maxBytes: self::DEFAULT_MAX_BYTES, maxRedirects: 0, userAgent: self::USER_AGENT);
    }

    #[Override]
    public function send(ServiceRequest $request): ServiceResponse
    {
        $url = $request->url->toString();

        if ('https' !== $request->url->scheme) {
            throw ServiceFailed::because(\sprintf('A service of LTI is called over HTTPS; "%s" is not.', $url));
        }

        $accept = '*/*';
        $headers = [];

        foreach ($request->headers as $name => $value) {
            if ('accept' === strtolower($name)) {
                $accept = $value;

                continue;
            }

            $headers[$name] = $value;
        }

        try {
            $response = $this->client->send($request->method, $request->url, $headers, $request->body ?? '', new ReadRule($accept));
        } catch (SafeHttpException $safeHttpException) {
            throw ServiceFailed::because(\sprintf('The service at "%s" cannot be reached: %s', $url, $safeHttpException->getMessage()), $safeHttpException);
        }

        if (!$response->complete) {
            throw ServiceFailed::because(\sprintf('The answer of "%s" is larger than the limits of the client.', $url));
        }

        return new ServiceResponse($response->status, $response->headers, $response->body);
    }

    #[Override]
    public function reset(): void
    {
        $this->client->reset();
    }
}
