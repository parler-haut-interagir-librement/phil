<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Signing;

use JsonException;
use Override;
use Phil\PagedMedia\Gotenberg\Exception\CertificateUnavailable;
use SensitiveParameter;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A certificate kept in a key/value secrets engine, version 2, of HashiCorp Vault or OpenBao, read at
 * `<address>/v1/<mount>/data/<path>` with a token. The secret holds the PEM certificate under `certificate` and its
 * PEM private key under `key`.
 */
final readonly class VaultCertificateSource implements CertificateSource
{
    public function __construct(
        private HttpClientInterface $client,
        private string $address,
        #[SensitiveParameter]
        private string $token,
        private string $path,
        private string $mount = 'secret',
    ) {
    }

    #[Override]
    public function certificate(): Certificate
    {
        $url = rtrim($this->address, '/').'/v1/'.trim($this->mount, '/').'/data/'.ltrim($this->path, '/');

        try {
            $response = $this->client->request('GET', $url, ['headers' => ['X-Vault-Token' => $this->token]]);
            $status = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (ExceptionInterface $exception) {
            throw CertificateUnavailable::because(\sprintf('The vault could not be reached at %s: %s', $url, $exception->getMessage()), $exception);
        }

        if (200 !== $status) {
            throw CertificateUnavailable::because(\sprintf('The vault answered %d for the secret %s.', $status, $url));
        }

        $secret = $this->secret($body);
        $certificate = $this->text($secret, 'certificate');
        $key = $this->text($secret, 'key');

        if (null === $certificate || null === $key) {
            throw CertificateUnavailable::because(\sprintf('The secret %s holds no "certificate" and "key" in PEM.', $url));
        }

        return new InlineCertificateSource($certificate, $key)->certificate();
    }

    /**
     * @return array<array-key, mixed>
     */
    private function secret(string $body): array
    {
        try {
            $json = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw CertificateUnavailable::because('The vault answered with something other than JSON.', $jsonException);
        }

        $data = \is_array($json) && \is_array($json['data'] ?? null) ? $json['data']['data'] ?? null : null;

        if (!\is_array($data)) {
            throw CertificateUnavailable::because('The vault answered without the "data" of a key/value secret, version 2.');
        }

        return $data;
    }

    /**
     * @param array<array-key, mixed> $secret
     */
    private function text(array $secret, string $field): ?string
    {
        $value = $secret[$field] ?? null;

        return \is_string($value) && '' !== $value ? $value : null;
    }
}
