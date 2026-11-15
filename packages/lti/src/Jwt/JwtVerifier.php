<?php

declare(strict_types=1);

namespace Phil\Lti\Jwt;

use DateTimeImmutable;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Phil\Lti\Contract\KeySetSource;
use Phil\Lti\Exception\InvalidToken;
use Phil\Lti\Key\KeySet;
use Phil\Lti\Key\RsaPublicKey;
use Phil\Url\Url;
use Psr\Clock\ClockInterface;
use Throwable;

/**
 * Verifies a token signed by the other side: its form is read within bounds by `Jwt::parse()` first; the algorithm is
 * `RS256`, whatever the token says; the key is the one its `kid` names in the key set of the other side, read again once
 * when the cached copy does not hold it; the signature verifies through `web-token/jwt-library`; and the times hold, by
 * the clock and a leeway of a few seconds for clocks that drift: `exp` is in the future, `iat` is not in the future nor
 * older than the longest age, `nbf`, when present, is past.
 */
final readonly class JwtVerifier
{
    public const int DEFAULT_LEEWAY = 5;

    /**
     * The oldest token accepted, in seconds after its `iat`, whatever its `exp` says.
     */
    public const int DEFAULT_MAX_AGE = 600;

    public function __construct(
        private KeySetSource $keySets,
        private ClockInterface $clock,
        private int $leeway = self::DEFAULT_LEEWAY,
        private int $maxAge = self::DEFAULT_MAX_AGE,
    ) {
    }

    /**
     * @throws InvalidToken
     */
    public function verify(string $token, Url $keySetUrl): Jwt
    {
        $jwt = Jwt::parse($token);
        $this->verifySignature($jwt, $this->keySets->keySet($keySetUrl), fn (): KeySet => $this->keySets->keySet($keySetUrl, true));
        $this->checkTimes($jwt->claims);

        return $jwt;
    }

    /**
     * @param (callable(): KeySet)|null $refreshed the set read again, when the first does not hold the key
     *
     * @throws InvalidToken
     */
    public function verifySignature(Jwt $jwt, KeySet $keys, ?callable $refreshed = null): void
    {
        if ('RS256' !== $jwt->algorithm()) {
            throw InvalidToken::because('A token of LTI is signed with RS256.');
        }

        $key = $keys->find($jwt->keyId());

        if (!$key instanceof RsaPublicKey && null !== $refreshed) {
            $key = $refreshed()->find($jwt->keyId());
        }

        if (null === $key) {
            throw InvalidToken::because(null === $jwt->keyId() ? 'The token names no key, and the key set does not hold exactly one.' : \sprintf('The key set does not hold the key "%s".', $jwt->keyId()));
        }

        try {
            $jws = new CompactSerializer()->unserialize($jwt->signingInput.'.'.Base64Url::encode($jwt->signature));
            $verified = new JWSVerifier(new AlgorithmManager([new RS256()]))->verifyWithKey($jws, $key->jwk, 0);
        } catch (Throwable $throwable) {
            throw InvalidToken::because('The signature of the token does not verify.', $throwable);
        }

        if (!$verified) {
            throw InvalidToken::because('The signature of the token does not verify.');
        }
    }

    /**
     * @param array<string, mixed> $claims
     *
     * @throws InvalidToken
     */
    public function checkTimes(array $claims): void
    {
        $now = $this->now();
        $expires = $claims['exp'] ?? null;
        $issued = $claims['iat'] ?? null;

        if (!\is_int($expires) || !\is_int($issued)) {
            throw InvalidToken::because('A token carries its "exp" and its "iat" as integers.');
        }

        if ($expires <= $now - $this->leeway) {
            throw InvalidToken::because('The token has expired.');
        }

        if ($issued > $now + $this->leeway) {
            throw InvalidToken::because('The token is issued in the future.');
        }

        if ($issued < $now - $this->maxAge) {
            throw InvalidToken::because('The token is too old.');
        }

        $notBefore = $claims['nbf'] ?? null;

        if (null !== $notBefore && (!\is_int($notBefore) || $notBefore > $now + $this->leeway)) {
            throw InvalidToken::because('The token is not valid yet.');
        }
    }

    /**
     * The moment a token expires when it lives the longest age from now: until then its nonce must be remembered.
     */
    public function expiryOfNonce(): DateTimeImmutable
    {
        return $this->clock->now()->modify(\sprintf('+%d seconds', $this->maxAge + $this->leeway));
    }

    public function now(): int
    {
        return $this->clock->now()->getTimestamp();
    }
}
