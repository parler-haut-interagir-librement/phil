<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Application;

use JsonException;
use Phil\EmbedBundle\Domain\Exception\InvalidEmbed;
use Phil\EmbedBundle\Domain\Exception\InvalidEmbedToken;
use Phil\EmbedBundle\Domain\ValueObject\EmbedClaims;
use Phil\EmbedBundle\Domain\ValueObject\Origin;
use Phil\EmbedBundle\Domain\ValueObject\SigningKey;
use Psr\Clock\ClockInterface;
use SodiumException;

/**
 * Signs and reads embed tokens: `pe1.hs256.<key>.<claims>.<signature>`. The format's version and the algorithm's
 * identifier lead the token and are signed with it, so that neither can be changed nor downgraded; the claims are JSON
 * in base64url; the signature is HMAC-SHA-256, in base64url, over everything before it.
 *
 * The keys are an ordered list: the first signs, every one verifies the tokens that name it, so that a key is rotated
 * by putting the new one first and removing the old one once its tokens no longer matter. A token is compared in
 * constant time, its size and its claims bounded before they are read.
 */
final readonly class EmbedTokenCodec
{
    public const string FORMAT = 'pe1';

    public const string ALGORITHM = 'hs256';

    public const int MAX_LENGTH = 2048;

    /** @var non-empty-array<string, SigningKey> */
    private array $keys;

    private string $signer;

    /**
     * @param list<SigningKey> $keys the first signs, all verify
     *
     * @throws InvalidEmbed when there is no key, or two with the same identifier
     */
    public function __construct(
        array $keys,
        private ClockInterface $clock,
        private bool $allowLocalhost = false,
    ) {
        $byId = [];

        foreach ($keys as $key) {
            if (isset($byId[$key->id])) {
                throw InvalidEmbed::because(\sprintf('Two keys are named "%s".', $key->id));
            }

            $byId[$key->id] = $key;
        }

        if ([] === $byId) {
            throw InvalidEmbed::because('Embed tokens need one key at least.');
        }

        $this->keys = $byId;
        $this->signer = array_key_first($byId);
    }

    public function encode(EmbedClaims $claims): string
    {
        $data = ['e' => $claims->embed, 'o' => $claims->origin->toString(), 'i' => $claims->issuedAt];

        if (null !== $claims->theme) {
            $data['t'] = $claims->theme;
        }

        if (null !== $claims->subject) {
            $data['s'] = $claims->subject;
        }

        if (null !== $claims->expiresAt) {
            $data['x'] = $claims->expiresAt;
        }

        $signed = self::FORMAT.'.'.self::ALGORITHM.'.'.$this->signer.'.'.$this->base64(json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES));

        return $signed.'.'.$this->base64($this->mac($this->keys[$this->signer], $signed));
    }

    /**
     * @throws InvalidEmbedToken when the token is not one of these keys, still valid
     */
    public function decode(string $token): EmbedClaims
    {
        if (\strlen($token) > self::MAX_LENGTH) {
            throw InvalidEmbedToken::because(\sprintf('An embed token is %d bytes at most.', self::MAX_LENGTH));
        }

        $parts = explode('.', $token);

        if (5 !== \count($parts)) {
            throw InvalidEmbedToken::because('An embed token has five parts.');
        }

        [$format, $algorithm, $keyId, $payload, $signature] = $parts;

        if (self::FORMAT !== $format || self::ALGORITHM !== $algorithm) {
            throw InvalidEmbedToken::because(\sprintf('An embed token is of the format "%s" and the algorithm "%s".', self::FORMAT, self::ALGORITHM));
        }

        $key = $this->keys[$keyId] ?? throw InvalidEmbedToken::because('The key of the token is not one of the site.');
        $mac = $this->unbase64($signature);

        if (!hash_equals($this->mac($key, $format.'.'.$algorithm.'.'.$keyId.'.'.$payload), $mac)) {
            throw InvalidEmbedToken::because('The signature of the token does not match.');
        }

        $claims = $this->claims($this->unbase64($payload));

        if (null !== $claims->expiresAt && $claims->expiresAt <= $this->clock->now()->getTimestamp()) {
            throw InvalidEmbedToken::because('The token has expired.');
        }

        return $claims;
    }

    private function claims(string $json): EmbedClaims
    {
        try {
            $data = json_decode($json, true, 2, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidEmbedToken::because('The claims of the token are not a JSON object of scalar values.');
        }

        if (!\is_array($data) || [] !== array_diff(array_keys($data), ['e', 'o', 'i', 't', 's', 'x'])) {
            throw InvalidEmbedToken::because('The claims of the token are e, o, i, t, s and x.');
        }

        $embed = $data['e'] ?? null;
        $origin = $data['o'] ?? null;
        $issued = $data['i'] ?? null;
        $theme = $data['t'] ?? null;
        $subject = $data['s'] ?? null;
        $expires = $data['x'] ?? null;

        if (!\is_string($embed) || !\is_string($origin) || !\is_int($issued) || (null !== $theme && !\is_string($theme)) || (null !== $subject && !\is_string($subject)) || (null !== $expires && !\is_int($expires))) {
            throw InvalidEmbedToken::because('The claims of the token are not of their types.');
        }

        try {
            return EmbedClaims::of($embed, Origin::fromString($origin, $this->allowLocalhost), $theme, $subject, $issued, $expires);
        } catch (InvalidEmbed $invalidEmbed) {
            throw InvalidEmbedToken::because($invalidEmbed->getMessage());
        }
    }

    private function mac(SigningKey $key, string $signed): string
    {
        return hash_hmac('sha256', $signed, $key->secret, true);
    }

    private function base64(string $bytes): string
    {
        return sodium_bin2base64($bytes, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    private function unbase64(string $text): string
    {
        try {
            return sodium_base642bin($text, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        } catch (SodiumException) {
            throw InvalidEmbedToken::because('A part of the token is not base64url.');
        }
    }
}
