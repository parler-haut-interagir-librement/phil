<?php

declare(strict_types=1);

namespace Phil\Passwords\Breach\Adapter;

use Phil\Passwords\Breach\Contract\BreachedPasswordSource;
use Phil\Passwords\Breach\RangeResponse;
use Phil\Passwords\Exception\BreachCheckUnavailable;
use SensitiveParameter;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The Pwned Passwords range API, by k-anonymity: only the first five characters of the password's SHA-1 hash are
 * sent, and the hashes that start with them are compared here. Padding is asked by default, so that the size of the
 * answer tells an observer nothing of the prefix.
 *
 * Any failure — the network, a status other than 200, an answer slower than the timeout, larger than the bound, or
 * malformed — is a `BreachCheckUnavailable`, the one exception a circuit breaker around the source counts. The base URL
 * may point to a mirror that serves the same format.
 */
final readonly class PwnedPasswordsSource implements BreachedPasswordSource
{
    public const string BASE_URL = 'https://api.pwnedpasswords.com/range/';

    public const string USER_AGENT = 'phil-passwords';

    /** The largest answer read: a padded range is a few tens of kilobytes. */
    public const int MAX_BYTES = 1_048_576;

    private const int PREFIX_LENGTH = 5;

    public function __construct(
        private HttpClientInterface $client,
        private string $baseUrl = self::BASE_URL,
        private bool $padding = true,
        private float $timeout = 5.0,
        private string $userAgent = self::USER_AGENT,
        private int $maxBytes = self::MAX_BYTES,
    ) {
    }

    public function occurrences(#[SensitiveParameter] string $password): int
    {
        $hash = strtoupper(hash('sha1', $password));
        $maxBytes = $this->maxBytes;

        try {
            $response = $this->client->request('GET', rtrim($this->baseUrl, '/').'/'.substr($hash, 0, self::PREFIX_LENGTH), [
                'headers' => ['User-Agent' => $this->userAgent] + ($this->padding ? ['Add-Padding' => 'true'] : []),
                'timeout' => $this->timeout,
                'max_duration' => 2 * $this->timeout,
                'on_progress' => static function (int $downloaded) use ($maxBytes): void {
                    if ($downloaded > $maxBytes) {
                        throw BreachCheckUnavailable::tooLarge($maxBytes);
                    }
                },
            ]);

            $status = $response->getStatusCode();

            if (200 !== $status) {
                throw BreachCheckUnavailable::status($status);
            }

            $body = $response->getContent();
        } catch (ExceptionInterface $exception) {
            throw $exception->getPrevious() instanceof BreachCheckUnavailable ? $exception->getPrevious() : BreachCheckUnavailable::transport($exception);
        }

        return RangeResponse::occurrences($body, substr($hash, self::PREFIX_LENGTH));
    }
}
