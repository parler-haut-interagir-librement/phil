<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Benchmark;

use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\Consent\ConsentChoice;
use Phil\PrivacyBundle\Domain\Consent\ConsentToken;

/**
 * The consent cookie is read on every page that asks about a consent: its signature, then its payload.
 *
 * @BeforeMethods("setUp")
 *
 * @Groups({"privacy", "consent"})
 */
final class ConsentTokenBench
{
    private readonly ConsentToken $token;

    private string $value = '';

    public function __construct()
    {
        $this->token = new ConsentToken('a secret of the application');
    }

    public function setUp(): void
    {
        $this->value = $this->token->encode(new ConsentChoice(str_repeat('a', 32), '2026-11', ['analytics', 'marketing'], new DateTimeImmutable('2026-01-01T00:00:00Z')));
    }

    /**
     * @Revs(1000)
     *
     * @Iterations(5)
     */
    public function benchDecode(): void
    {
        $this->token->decode($this->value);
    }

    /**
     * @Revs(1000)
     *
     * @Iterations(5)
     */
    public function benchRefuseAForgedValue(): void
    {
        $this->token->decode('forged.value');
    }
}
