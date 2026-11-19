<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Infrastructure\SecretScanning\GitHub;

use OpenSSLAsymmetricKey;
use Phil\ApiKeysBundle\Application\ApiTokens;
use Phil\ApiKeysBundle\Domain\Model\ApiToken;
use Phil\ApiKeysBundle\Infrastructure\SecretScanning\Exception\UnreadableReport;
use SodiumException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The endpoint of GitHub's secret scanning partner program: GitHub posts the tokens it finds in public repositories,
 * signed with one of its keys; each one that is a token of this application is revoked at once.
 *
 * The body is read only once its ECDSA signature, over the raw body, verifies under the key its identifier names. The
 * answer lists each match by the SHA-256 of its token, in hexadecimal, as a `true_positive` when it is a token of
 * this application, a `false_positive` otherwise; it never holds a token.
 *
 * | Request | Answer |
 * |---|---|
 * | Signed, readable | `200`, the labels |
 * | No identifier or signature, an unknown key, a signature that does not verify | `403`, nothing read |
 * | Longer than 1 MiB | `413` |
 * | Signed but not a list of matches | `400` |
 */
final readonly class GitHubSecretScanningController
{
    public const string ROUTE = 'phil_api_keys_github_secret_scanning';

    public const string IDENTIFIER_HEADER = 'Github-Public-Key-Identifier';

    public const string SIGNATURE_HEADER = 'Github-Public-Key-Signature';

    /** The longest signature, in base64: an ECDSA signature on P-256 in DER is 72 bytes at most. */
    public const int MAX_SIGNATURE_LENGTH = 256;

    public const string TRUE_POSITIVE = 'true_positive';

    public const string FALSE_POSITIVE = 'false_positive';

    public function __construct(
        private ApiTokens $tokens,
        private GitHubPublicKeys $keys,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $identifier = (string) $request->headers->get(self::IDENTIFIER_HEADER);
        $signature = (string) $request->headers->get(self::SIGNATURE_HEADER);

        if (1 !== preg_match('/^[\x21-\x7e]{1,256}$/D', $identifier) || '' === $signature || \strlen($signature) > self::MAX_SIGNATURE_LENGTH) {
            return new Response('', Response::HTTP_FORBIDDEN);
        }

        $body = $request->getContent();

        if (\strlen($body) > LeakReport::MAX_BYTES) {
            return new Response('', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        if (!$this->verifies($body, $identifier, $signature)) {
            return new Response('', Response::HTTP_FORBIDDEN);
        }

        try {
            $report = LeakReport::fromJson($body);
        } catch (UnreadableReport) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        $labels = [];

        foreach ($report->matches as $match) {
            $labels[] = [
                'token_hash' => hash('sha256', $match['token']),
                'token_type' => $match['type'],
                'label' => $this->tokens->revokeLeaked($match['token']) instanceof ApiToken ? self::TRUE_POSITIVE : self::FALSE_POSITIVE,
            ];
        }

        return new JsonResponse($labels);
    }

    /**
     * Whether the signature, in base64, verifies the body under the key GitHub publishes under that identifier.
     */
    private function verifies(string $body, string $identifier, string $signature): bool
    {
        try {
            $decoded = sodium_base642bin($signature, \SODIUM_BASE64_VARIANT_ORIGINAL);
        } catch (SodiumException) {
            return false;
        }

        $pem = $this->keys->key($identifier);

        return null !== $pem
            && ($key = openssl_pkey_get_public($pem)) instanceof OpenSSLAsymmetricKey
            && 1 === openssl_verify($body, $decoded, $key, \OPENSSL_ALGO_SHA256);
    }
}
