<?php

declare(strict_types=1);

namespace Phil\ErrorTracking;

use Phil\ErrorTracking\Internal\Text;
use Phil\IpAnonymizer\Contract\IpAnonymizer;
use Phil\IpAnonymizer\IpAddress;
use SensitiveParameter;

/**
 * The request an event happened in, reduced: its method, its address, its route, its headers by lower-case name and
 * the client's address as an anonymizer left it — never the body, never the raw address. The headers that carry a
 * client's address on its way through proxies, {@see self::CLIENT_ADDRESS_HEADERS}, are dropped: the address they
 * hold is the raw one. The scrubber still filters the query, the secrets and the e-mail addresses before the summary
 * is written anywhere.
 */
final readonly class RequestSummary
{
    public const int MAX_URL_LENGTH = 2048;

    public const int MAX_HEADERS = 50;

    public const int MAX_HEADER_LENGTH = 1024;

    /**
     * The headers, by lower-case name, through which proxies, load balancers and content delivery networks pass on the
     * client's raw address; never kept.
     */
    public const array CLIENT_ADDRESS_HEADERS = [
        'forwarded', 'x-forwarded-for', 'x-original-forwarded-for', 'x-forwarded', 'forwarded-for', 'x-real-ip',
        'cf-connecting-ip', 'cf-connecting-ipv6', 'true-client-ip', 'x-client-ip', 'client-ip', 'x-cluster-client-ip',
        'fastly-client-ip', 'x-originating-ip', 'x-remote-ip', 'x-remote-addr', 'x-envoy-external-address',
    ];

    public string $method;

    public string $url;

    public ?string $route;

    /**
     * @var array<string, string>
     */
    public array $headers;

    public ?string $clientAddress;

    /**
     * @param array<array-key, string> $headers       the headers, those of {@see self::CLIENT_ADDRESS_HEADERS} dropped
     * @param ?string                  $clientAddress the address an anonymizer made, see anonymized()
     */
    public function __construct(
        string $method,
        string $url,
        ?string $route = null,
        array $headers = [],
        ?string $clientAddress = null,
    ) {
        $this->method = strtoupper(Text::clip($method, 16));
        $this->url = Text::clip($url, self::MAX_URL_LENGTH);
        $this->route = null === $route ? null : Text::clip($route, 255);
        $this->clientAddress = null === $clientAddress ? null : Text::clip($clientAddress, 64);
        $kept = [];

        foreach (\array_slice($headers, 0, self::MAX_HEADERS, true) as $name => $value) {
            $name = strtolower(Text::clip((string) $name, 64));

            if (!\in_array($name, self::CLIENT_ADDRESS_HEADERS, true)) {
                $kept[$name] = Text::clip($value, self::MAX_HEADER_LENGTH);
            }
        }

        $this->headers = $kept;
    }

    /**
     * The summary with the client's raw address turned into what the anonymizer keeps; a text that is not an address
     * keeps nothing.
     *
     * @param array<array-key, string> $headers
     */
    public static function anonymized(string $method, string $url, ?string $route, array $headers, #[SensitiveParameter] ?string $clientAddress, IpAnonymizer $anonymizer): self
    {
        $address = null === $clientAddress ? null : IpAddress::tryParse($clientAddress);

        return new self($method, $url, $route, $headers, $address instanceof IpAddress ? $anonymizer->anonymize($address) : null);
    }
}
