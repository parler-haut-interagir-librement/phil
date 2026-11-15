<?php

declare(strict_types=1);

namespace Phil\Lti\Jwt;

use JsonException;
use Phil\Lti\Exception\InvalidToken;

/**
 * A compact JSON Web Signature read but not yet verified: its header, its claims, the bytes it signs and its signature.
 * Only the form is checked here: three parts, each in URL-safe base 64, the first two JSON objects of bounded size and
 * depth, an algorithm named in the header.
 */
final readonly class Jwt
{
    /**
     * The longest token read, in bytes: a launch with its claims stays far below.
     */
    public const int MAX_LENGTH = 65_536;

    /**
     * The deepest nesting of the JSON of a part.
     */
    public const int MAX_DEPTH = 16;

    /**
     * @param array<string, mixed> $header
     * @param array<string, mixed> $claims
     */
    private function __construct(
        public array $header,
        public array $claims,
        public string $signingInput,
        public string $signature,
    ) {
    }

    /**
     * @throws InvalidToken when the token is not a compact JSON Web Signature
     */
    public static function parse(string $token): self
    {
        if (\strlen($token) > self::MAX_LENGTH) {
            throw InvalidToken::because(\sprintf('A token is %d bytes at most.', self::MAX_LENGTH));
        }

        $parts = explode('.', $token);

        if (3 !== \count($parts)) {
            throw InvalidToken::because('A token has three parts separated by dots.');
        }

        [$header, $claims, $signature] = $parts;
        $decodedHeader = self::object($header);

        if (!\is_string($decodedHeader['alg'] ?? null)) {
            throw InvalidToken::because('The header of a token names its algorithm.');
        }

        if (isset($decodedHeader['crit']) || \array_key_exists('b64', $decodedHeader)) {
            throw InvalidToken::because('A token with critical header parameters, or an unencoded payload, is not read.');
        }

        return new self($decodedHeader, self::object($claims), $header.'.'.$claims, Base64Url::decode($signature));
    }

    public function algorithm(): string
    {
        $algorithm = $this->header['alg'];

        return \is_string($algorithm) ? $algorithm : '';
    }

    public function keyId(): ?string
    {
        $kid = $this->header['kid'] ?? null;

        return \is_string($kid) && '' !== $kid ? $kid : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function object(string $part): array
    {
        try {
            $value = json_decode(Base64Url::decode($part), true, self::MAX_DEPTH, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);
        } catch (JsonException $jsonException) {
            throw InvalidToken::because('A part of the token is not JSON.', $jsonException);
        }

        if (!\is_array($value) || ([] !== $value && array_is_list($value))) {
            throw InvalidToken::because('A part of the token is not a JSON object.');
        }

        $object = [];

        foreach ($value as $name => $member) {
            $object[(string) $name] = $member;
        }

        return $object;
    }
}
