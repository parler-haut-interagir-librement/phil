<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Benchmark;

use Generator;
use Jose\Component\Core\JWK;
use Phil\IdentityProviderBundle\Application\Jose\Jws;
use Phil\IdentityProviderBundle\Application\Jose\PrivateKey;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;

/**
 * What every token request and every userinfo call pays: an ID token signed, and an access token verified, with
 * each algorithm of the provider.
 */
final readonly class JwsBench
{
    private const array CLAIMS = ['iss' => 'https://id.example', 'sub' => '01JCONFORMANCE0000000000000', 'aud' => 'app', 'iat' => 1_794_700_000, 'exp' => 1_794_700_600, 'nonce' => 'n-0S6_WzA2Mj'];

    /** @var array<string, PrivateKey> */
    private array $keys;

    /** @var array<string, JWK> */
    private array $public;

    /** @var array<string, string> */
    private array $tokens;

    public function __construct()
    {
        $keys = [];
        $public = [];
        $tokens = [];

        foreach (SigningAlgorithm::cases() as $algorithm) {
            $key = PrivateKey::generate($algorithm, $algorithm->value);
            $keys[$algorithm->value] = $key;
            $public[$algorithm->value] = new JWK($key->publicJwk());
            $tokens[$algorithm->value] = Jws::encode(self::CLAIMS, $key);
        }

        [$this->keys, $this->public, $this->tokens] = [$keys, $public, $tokens];
    }

    /**
     * @return Generator<string, array{algorithm: string}>
     */
    public function algorithms(): Generator
    {
        foreach (SigningAlgorithm::cases() as $algorithm) {
            yield $algorithm->value => ['algorithm' => $algorithm->value];
        }
    }

    /**
     * @ParamProviders("algorithms")
     *
     * @param array{algorithm: string} $parameters
     */
    public function benchSign(array $parameters): void
    {
        Jws::encode(self::CLAIMS, $this->keys[$parameters['algorithm']]);
    }

    /**
     * @ParamProviders("algorithms")
     *
     * @param array{algorithm: string} $parameters
     */
    public function benchVerify(array $parameters): void
    {
        $key = $this->public[$parameters['algorithm']];
        Jws::decode($this->tokens[$parameters['algorithm']], static fn (string $kid): JWK => $key);
    }
}
